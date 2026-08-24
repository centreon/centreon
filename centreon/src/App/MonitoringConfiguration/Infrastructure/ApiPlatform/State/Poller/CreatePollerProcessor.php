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

        // In PullWSS the Central only accepts the poller's websocket once Gorgone has
        // re-read its node list, so every creation must trigger the sync — same as the
        // legacy poller form. It is sent from here, after the command bus committed, and
        // not from a PollerCreated handler: those run inside the create-poller
        // transaction, where Gorgone — reading the database on its own connection —
        // would not see the new poller yet.
        $this->synchronizeGorgoneNodes($model);

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
     * A Gorgone outage must not fail a creation that is already committed: the poller is
     * persisted and returned, and it is picked up by the next sync (legacy poller form save,
     * configuration export or Gorgone restart).
     *
     * Logged at error level, not warning: the record must escape the fingers_crossed buffer
     * so an operator sees that this poller was not announced to the Central.
     */
    private function synchronizeGorgoneNodes(Poller $poller): void
    {
        try {
            $this->gorgoneNodesSynchronizer->synchronize();
        } catch (\Throwable $exception) {
            /** @var PollerId $pollerId */
            $pollerId = $poller->id();

            $this->logger->error('Failed to trigger Gorgone nodes sync', [
                'poller_id' => $pollerId->value,
                'exception' => ExceptionFormatter::format($exception),
            ]);
        }
    }
}
