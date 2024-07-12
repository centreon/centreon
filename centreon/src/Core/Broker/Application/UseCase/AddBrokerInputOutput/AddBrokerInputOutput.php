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

namespace Core\Broker\Application\UseCase\AddBrokerInputOutput;

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
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\NewBrokerInputOutput;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;

/**
 * @phpstan-import-type _BrokerInputOutputParameter from \Core\Broker\Domain\Model\BrokerInputOutput
 */
final class AddBrokerInputOutput
{
    use LoggerTrait, VaultTrait;

    public function __construct(
        private readonly WriteBrokerInputOutputRepositoryInterface $writeOutputRepository,
        private readonly ReadBrokerInputOutputRepositoryInterface $readOutputRepository,
        private readonly ContactInterface $user,
        private readonly BrokerInputOutputValidator $validator,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly FeatureFlags $flags,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::BROKER_VAULT_PATH);
    }

    /**
     * @param AddBrokerInputOutputRequest $request
     * @param AddBrokerInputOutputPresenterInterface $presenter
     */
    public function __invoke(AddBrokerInputOutputRequest $request, AddBrokerInputOutputPresenterInterface $presenter): void
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
                null === ($type = $this->readOutputRepository->findType($request->tag, $request->type))
                || [] === ($outputFields = $this->readOutputRepository->findParametersByType($request->type))
            ) {
                throw BrokerException::idDoesNotExist('type', $request->type);
            }

            $this->validator->validateParameters($outputFields, $request->parameters);

            /** @var _BrokerInputOutputParameter[] $validatedParameters */
            $validatedParameters = $request->parameters;

            $newOutput = new NewBrokerInputOutput(
                tag: $request->tag,
                type: $type,
                name: $request->name,
                parameters: $validatedParameters
            );

            if ($this->flags->isEnabled('vault_broker') && $this->writeVaultRepository->isVaultConfigured() === true) {
                $this->uuid = $this->getBrokerVaultUuid($request->brokerId);
                $newOutput = $this->saveInVault($newOutput, $outputFields);
            }

            $outputId = $this->writeOutputRepository->add(
                $newOutput,
                $request->brokerId,
                $outputFields
            );

            if (! ($output = $this->readOutputRepository->findByIdAndBrokerId(
                $request->tag,
                $outputId,
                $request->brokerId
            ))) {
                throw BrokerException::inputOutputNotFound($request->brokerId, $outputId);
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
                new ErrorResponse(BrokerException::addBrokerInputOutput())
            );
            $this->error((string) $ex);
        }
    }

    private function createResponse(BrokerInputOutput $output, int $brokerId): AddBrokerInputOutputResponse
    {
        return new AddBrokerInputOutputResponse(
            id: $output->getId(),
            brokerId: $brokerId,
            name: $output->getName(),
            type: new TypeDto($output->getType()->id, $output->getType()->name),
            parameters: $output->getParameters()
        );
    }

    private function getBrokerVaultUuid(int $configId): ?string
    {
        $vaultPath = $this->readOutputRepository->findVaultPathByBrokerId($configId);
        if ($vaultPath === null) {

            return null;
        }

        return $this->getUuidFromPath($vaultPath);
    }

    /**
     * @param NewBrokerInputOutput $inputOutput
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $inputOutputFields
     *
     * @return NewBrokerInputOutput
     */
    private function saveInVault(NewBrokerInputOutput $inputOutput, array $inputOutputFields): NewBrokerInputOutput
    {
        $updatedParameters = $inputOutput->getParameters();

        foreach ($updatedParameters as $paramName => $paramValue) {
            if (is_array($inputOutputFields[$paramName])) {
                if (! is_array($paramValue)) {
                    // for phpstan, should not happen.
                    continue;
                }
                foreach ($paramValue as $groupIndex => $groupedParams) {
                    if (isset($groupedParams['type']) && $groupedParams['type'] === 'password') {
                        /** @var array{type:string,name:string,value:string|int} $groupedParams */
                        $vaultKey = implode('_', [$inputOutput->getName(), $paramName, $groupedParams['name']]);
                        $vaultPaths = $this->writeVaultRepository->upsert(
                            $this->uuid,
                            [$vaultKey => $groupedParams['value']],
                            []
                        );
                        $vaultPath = $vaultPaths[$vaultKey];
                        $this->uuid ??= $this->getUuidFromPath($vaultPath);
                        /** @var array<array<array{value:string}>> $updatedParameters */
                        $updatedParameters[$paramName][$groupIndex]['value'] = $vaultPath;
                    }
                }
            } elseif (
                array_key_exists($paramName, $inputOutputFields)
                && $inputOutputFields[$paramName]->getType() === 'password'
            ) {
                if (! is_string($paramValue) && ! is_int($paramValue)) {
                    // for phpstan, should not happen.
                    continue;
                }
                $vaultKey = implode('_', [$inputOutput->getName(), $paramName]);
                $vaultPaths = $this->writeVaultRepository->upsert($this->uuid, [$vaultKey => $paramValue], []);
                $vaultPath = $vaultPaths[$vaultKey];
                $this->uuid ??= $this->getUuidFromPath($vaultPath);
                $updatedParameters[$paramName] = $vaultPath;
            }
        }

        return new NewBrokerInputOutput(
            tag: $inputOutput->getTag(),
            type: $inputOutput->getType(),
            name: $inputOutput->getName(),
            parameters: $updatedParameters,
        );
    }
}
