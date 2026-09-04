<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreatePollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\GorgoneNodesSyncFailedException;
use App\MonitoringConfiguration\Domain\Exception\PollerAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Domain\Service\GorgoneNodesSynchronizer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreatePollerInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerResource;
use App\MonitoringConfiguration\Infrastructure\PollerInstallationCommandFactory;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Repository\EngineSecretsRepository;
use App\Shared\Infrastructure\Logging\ExceptionFormatter;
use App\Shared\Infrastructure\TransformerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreatePollerInput, PollerResource>
 */
final readonly class CreatePollerProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Poller, PollerResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourcePollerTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private PollerRepository $pollerRepository,
        private PollerTokenRepository $pollerTokenRepository,
        private EngineSecretsRepository $engineSecretsRepository,
        private GorgoneNodesSynchronizer $gorgoneNodesSynchronizer,
        private LoggerInterface $logger,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): PollerResource
    {
        $pollerName = new PollerName($data->name);
        if ($this->pollerRepository->findOneByName($pollerName) instanceof Poller) {
            throw new PollerAlreadyExistsException(
                criteria: ['poller_name' => $pollerName],
                message: 'Poller with this name already exists',
                code: Response::HTTP_CONFLICT
            );
        }

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $centralAddress = new CentralAddress($data->centralAddress);

        $command = new CreatePollerCommand(
            name: $pollerName,
            pollerType: PollerTypeEnum::from($data->pollerType),
            address: new PollerAddress($data->address),
            creatorId: $credentialUser->credential->userId->value,
            centralAddress: $centralAddress,
            // Both install.sh paths (vm and docker) always provision the gorgone pullwss
            // module, on-premise as well as on Cloud, so the persisted communication type
            // must match. ZMQ stays available through the legacy poller wizard.
            gorgoneCommunicationType: GorgoneCommunicationTypeEnum::PullWss,
        );

        $token = $this->pollerTokenRepository->getValidPollerTokenByName($data->pollerTokenName);
        $appSecret = $this->engineSecretsRepository->getAppSecret();
        $salt = $this->engineSecretsRepository->getSalt();

        $model = $this->commandBus->execute($command);
        Assert::isInstanceOf($model, Poller::class);

        // Not from a PollerCreated handler: those run inside the create-poller transaction,
        // and Gorgone reads the database on its own connection.
        $this->synchronizeGorgoneNodes($model->id(), $model->name);

        // Use the normalized value, not the raw input: the stored poller and the
        // returned command must match what GET /installation-command/{id} generates.
        $factory = PollerInstallationCommandFactory::fromPoller(
            $model,
            $token,
            $appSecret,
            $salt,
            $this->isCloudPlatform,
            $centralAddress->value,
        );

        $resource = $this->transformer->transform($model);
        $resource->installationCommand = $factory->generate();

        return $resource;
    }

    /**
     * Fire-and-forget: the poller is already committed, so a Gorgone outage must not fail the
     * creation. There is no automatic retry — the only re-sync paths are manual operator
     * actions — so the record has to carry the consequence and the remedy.
     *
     * Logged at error level, not warning: the record must escape the fingers_crossed buffer.
     * Only GorgoneNodesSyncFailedException is absorbed; a wiring error still propagates.
     */
    private function synchronizeGorgoneNodes(PollerId $pollerId, PollerName $pollerName): void
    {
        try {
            $this->gorgoneNodesSynchronizer->synchronize();
        } catch (GorgoneNodesSyncFailedException $exception) {
            $this->logger->error(
                'Poller created but not announced to the Central: re-save it from the legacy poller form to retry',
                [
                    'poller_id' => $pollerId->value,
                    'poller_name' => $pollerName->value,
                    'source' => 'poller_api',
                    'exception' => ExceptionFormatter::format($exception),
                ]
            );
        }
    }
}
