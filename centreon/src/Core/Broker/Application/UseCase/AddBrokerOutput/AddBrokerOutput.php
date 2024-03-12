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

namespace Core\Broker\Application\UseCase\AddBrokerOutput;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\Repository\ReadBrokerOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerOutput;
use Core\Broker\Domain\Model\NewBrokerOutput;

/**
 * @phpstan-import-type _BrokerOutputParameter from \Core\Broker\Domain\Model\BrokerOutput
 */
final class AddBrokerOutput
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteBrokerOutputRepositoryInterface $writeOutputRepository,
        private readonly ReadBrokerOutputRepositoryInterface $readOutputRepository,
        private readonly ContactInterface $user,
        private readonly BrokerOutputValidator $validator,
    ) {
    }

    /**
     * @param AddBrokerOutputRequest $request
     * @param AddBrokerOutputPresenterInterface $presenter
     */
    public function __invoke(AddBrokerOutputRequest $request, AddBrokerOutputPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_BROKER_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to add a broker output",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(BrokerException::editNotAllowed()->getMessage())
                );

                return;
            }

            $this->validator->brokerIsValidOrFail($request->brokerId);

            if (
                null === ($type = $this->readOutputRepository->findType($request->type))
                || [] === ($outputFields = $this->readOutputRepository->findParametersByType($request->type))
            ) {
                throw BrokerException::idDoesNotExist('type', $request->type);
            }

            $this->validator->validateParameters($outputFields, $request->parameters);

            /** @var _BrokerOutputParameter[] $validatedParameters */
            $validatedParameters = $request->parameters;

            $outputId = $this->writeOutputRepository->add(
                new NewBrokerOutput(
                    type: $type,
                    name: $request->name,
                    parameters: $validatedParameters
                ),
                $request->brokerId,
                $outputFields
            );

            if (! ($output = $this->readOutputRepository->findByIdAndBrokerId($outputId, $request->brokerId))) {
                throw BrokerException::outputNotFound($request->brokerId, $outputId);
            }

            $presenter->presentResponse(
                $this->createResponse($output, $request->brokerId)
            );
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (BrokerException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    BrokerException::CODE_CONFLICT => new ConflictResponse($ex),
                    BrokerException::CODE_INVALID => new InvalidArgumentResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(BrokerException::addBrokerOutput())
            );
            $this->error((string) $ex);
        }
    }

    private function createResponse(BrokerOutput $output, int $brokerId): AddBrokerOutputResponse
    {
        return new AddBrokerOutputResponse(
            id: $output->getId(),
            brokerId: $brokerId,
            name: $output->getName(),
            type: new TypeDto($output->getType()->id, $output->getType()->name),
            parameters: $output->getParameters()
        );
    }
}
