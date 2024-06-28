<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Broker\Application\UseCase\UpdateStreamConnectorFile;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;

final class UpdateStreamConnectorFile
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteBrokerInputOutputRepositoryInterface $writeOutputRepository,
        private readonly ReadBrokerInputOutputRepositoryInterface $readOutputRepository,
        private readonly WriteBrokerRepositoryInterface $fileRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param UpdateStreamConnectorFileRequest $request
     * @param UpdateStreamConnectorFilePresenterInterface $presenter
     */
    public function __invoke(UpdateStreamConnectorFileRequest $request, UpdateStreamConnectorFilePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_BROKER_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to edit a broker output",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(BrokerException::editNotAllowed()->getMessage())
                );

                return;
            }

            if (! ($output = $this->readOutputRepository->findByIdAndBrokerId('output', $request->outputId, $request->brokerId))) {
                throw BrokerException::inputOutputNotFound($request->brokerId, $request->outputId);
            }

            if ($output->getType()->name !== 'lua') {
                throw BrokerException::outputTypeInvalidForThisAction($output->getType()->name);
            }

            if (! \json_decode($request->fileContent)) {
                throw BrokerException::invalidJsonContent();
            }

            $filePath = $this->createFile($request->fileContent);

            $this->deletePreviousFile($output);

            $this->updateOutput($request->brokerId, $output, $filePath);

            $presenter->presentResponse(
                new UpdateStreamConnectorFileResponse($filePath)
            );
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (BrokerException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    BrokerException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(BrokerException::updateBrokerInputOutput())
            );
            $this->error((string) $ex);
        }
    }

    private function createFile(string $content): string
    {
        $timestamp = time();
        $filePath = "/var/lib/centreon/stream_connector_{$timestamp}.json";

        try {
            $this->fileRepository->create($filePath, $content);
        } catch (\Throwable $ex) {
            $this->error((string) $ex);

            throw BrokerException::errorWhenCreatingFile($filePath);
        }

        return $filePath;
    }

    private function deletePreviousFile(BrokerInputOutput $output): void
    {
        $pathParameter = $output->getParameters()['path'] ?? null;
        if ($pathParameter && is_string($pathParameter)) {
            try {
                $this->fileRepository->delete($pathParameter);
            } catch (\Throwable $ex) {
                $this->info('Could not delete file', ['file_path' => $pathParameter, 'message' => (string) $ex]);
            }
        }
    }

    private function updateOutput(int $brokerId, BrokerInputOutput $output, string $filePath): void
    {
        $parameters = $output->getParameters();
        $parameters['path'] = $filePath;
        $output->setParameters($parameters);

        $fields = $this->readOutputRepository->findParametersByType($output->getType()->id);
        $this->writeOutputRepository->update($output, $brokerId, $fields);
    }
}
