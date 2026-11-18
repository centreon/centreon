<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Application\Service;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Gorgone\Application\EngineConfiguration\EngineConfigurationBuilder;
use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Core\Gorgone\Application\Repository\CollectorDeployerInterface;
use Core\Gorgone\Application\Repository\CollectorEnrolmentRepositoryInterface;
use Core\Gorgone\Application\Repository\CollectorProvisionerInterface;
use Core\Gorgone\Domain\Model\CollectorEnrolment;
use Core\Gorgone\Domain\Model\CreatedCollectorEnrolment;

/**
 * Adding a collector (a poller run by centreon-engine) in one command: the admin creates an
 * enrolment, the collector presents its token to gorgone-server, and this service does the rest
 * (poller of a fleet enrolment, first configuration, removal) from the web cron.
 *
 * Tokens are random, given once and never kept: gorgone-server gets their SHA-256 only.
 */
final class CollectorEnrolmentService
{
    use LoggerTrait;
    public const FEATURE_FLAG = 'collector_enrolment';

    /** Default validity of a token: 24 hours. */
    public const DEFAULT_VALIDITY_SECONDS = 86400;

    /** Longest validity of a token: 30 days. */
    public const MAX_VALIDITY_SECONDS = 2592000;

    /** Port of the agent gateway of gorgone-server when no collector_server_url is set. */
    public const DEFAULT_AGENT_PORT = 5556;

    /** Image of the docker run line when no collector_image is set. */
    public const DEFAULT_IMAGE = 'centreon-engine';
    public const OPTION_SERVER_URL = 'collector_server_url';
    public const OPTION_BROKER_ADDRESS = 'collector_broker_address';
    public const OPTION_IMAGE = 'collector_image';

    /** Name rules of the poller wizard (40 characters), restricted to what a host name can be. */
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/';

    /** Flags the export of a collector needs: structured configuration sent through the API. */
    private const REQUIRED_FLAGS = [
        GorgoneCommandService::FEATURE_FLAG,
        EngineConfigurationBuilder::FEATURE_FLAG,
    ];

    /** @var \Closure(int): string */
    private readonly \Closure $randomBytes;

    /**
     * @param FeatureFlags $featureFlags
     * @param CollectorEnrolmentRepositoryInterface $enrolmentRepository
     * @param CollectorProvisionerInterface $provisioner
     * @param CollectorDeployerInterface $deployer
     * @param OptionServiceInterface $optionService
     * @param ConfigurationLoaderApiInterface $gorgoneConfiguration
     * @param (\Closure(int): string)|null $randomBytes source of the tokens, random_bytes() by default
     */
    public function __construct(
        private readonly FeatureFlags $featureFlags,
        private readonly CollectorEnrolmentRepositoryInterface $enrolmentRepository,
        private readonly CollectorProvisionerInterface $provisioner,
        private readonly CollectorDeployerInterface $deployer,
        private readonly OptionServiceInterface $optionService,
        private readonly ConfigurationLoaderApiInterface $gorgoneConfiguration,
        ?\Closure $randomBytes = null,
    ) {
        $this->randomBytes = $randomBytes ?? random_bytes(...);
    }

    public function isEnabled(): bool
    {
        return $this->featureFlags->isEnabled(self::FEATURE_FLAG);
    }

    /**
     * Create an enrolment: for a named collector (a new poller, created now, or an existing one)
     * with a single-use token, or for a fleet (the poller is created when a machine enrols, named
     * after its host name) with a token used up to $maxUses times.
     *
     * @param string|null $name new poller to create
     * @param int|null $pollerId existing poller
     * @param bool $isFleet
     * @param int|null $expiresIn seconds, DEFAULT_VALIDITY_SECONDS when null
     * @param int|null $maxUses fleet only, null for no limit until it expires
     * @param int $creatorId
     * @param string $creatorName
     *
     * @throws CollectorEnrolmentException
     * @throws \Throwable
     *
     * @return CreatedCollectorEnrolment
     */
    public function create(
        ?string $name,
        ?int $pollerId,
        bool $isFleet,
        ?int $expiresIn,
        ?int $maxUses,
        int $creatorId,
        string $creatorName,
    ): CreatedCollectorEnrolment {
        $this->assertEnabled();
        $expiresIn ??= self::DEFAULT_VALIDITY_SECONDS;
        if ($expiresIn < 60 || $expiresIn > self::MAX_VALIDITY_SECONDS) {
            throw CollectorEnrolmentException::invalid(sprintf(
                'expires_in must be between 60 and %d seconds',
                self::MAX_VALIDITY_SECONDS
            ));
        }
        if (count(array_filter([$name !== null, $pollerId !== null, $isFleet])) !== 1) {
            throw CollectorEnrolmentException::invalid('Give exactly one of name, poller_id or fleet');
        }
        if ($maxUses !== null && (! $isFleet || $maxUses < 1)) {
            throw CollectorEnrolmentException::invalid('max_uses is a positive number, for a fleet token only');
        }

        $pollerName = null;
        if ($name !== null) {
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw CollectorEnrolmentException::invalid(
                    'name must be 1 to 40 characters among letters, digits, ".", "_" and "-"'
                );
            }
            foreach ($this->provisioner->findPollers() as $poller) {
                if (strcasecmp($poller['name'], $name) === 0 || strcasecmp($poller['address'], $name) === 0) {
                    throw CollectorEnrolmentException::invalid(sprintf('A poller "%s" already exists', $name));
                }
            }
            $pollerId = $this->provisioner->createPoller(
                $name,
                $name,
                $this->findBrokerAddress(),
                $creatorId,
                $creatorName
            );
            $pollerName = $name;
            $this->info('Collector enrolment: poller created', ['poller_id' => $pollerId, 'name' => $name]);
        } elseif ($pollerId !== null) {
            $pollerName = $this->findPollerName($pollerId);
            if ($pollerName === null) {
                throw CollectorEnrolmentException::invalid(sprintf('Poller #%d does not exist', $pollerId));
            }
            $this->provisioner->markRunByCentreonEngine($pollerId);
        }

        $token = bin2hex(($this->randomBytes)(32));
        $id = bin2hex(($this->randomBytes)(16));
        $this->enrolmentRepository->addToken(
            $id,
            hash('sha256', $token),
            $pollerId,
            $pollerName,
            $expiresIn,
            $maxUses
        );

        $environment = [
            'CENTREON_ENGINE__SERVER_URL' => $this->findServerUrl(),
            'CENTREON_ENGINE__ENROLMENT_TOKEN' => $token,
        ];

        return new CreatedCollectorEnrolment(
            id: $id,
            token: $token,
            isFleet: $isFleet,
            pollerId: $pollerId,
            pollerName: $pollerName,
            expiresAt: time() + $expiresIn,
            maxUses: $isFleet ? $maxUses : 1,
            environment: $environment,
            command: $this->buildCommand($environment, $pollerName),
        );
    }

    /**
     * @throws CollectorEnrolmentException
     * @throws \Throwable
     *
     * @return list<CollectorEnrolment>
     */
    public function findEnrolments(): array
    {
        $this->assertEnabled();

        return $this->enrolmentRepository->findEnrolments();
    }

    /**
     * Web cron: creates the pollers of the fleet enrolments, sends their first configuration to the
     * collectors that connected without one, and deletes the nodes of deleted pollers. Each step
     * is retried at the next run when it fails.
     *
     * @return list<string> what was done, for the cron log
     */
    public function synchronize(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        $done = [];
        $enrolments = $this->enrolmentRepository->findEnrolments();
        $pollers = $this->provisioner->findPollers();
        $pollerIds = array_column($pollers, 'id');
        $creator = null;

        foreach ($enrolments as $enrolment) {
            if ($enrolment->state !== CollectorEnrolment::STATE_PENDING) {
                continue;
            }
            try {
                [$name, $address] = $this->chooseIdentity($enrolment, $pollers);
                // Nobody is logged in here: an administrator is the creator of what the wizard creates.
                $creator ??= $this->provisioner->findAdministrator();
                $pollerId = $this->provisioner->createPoller(
                    $name,
                    $address,
                    $this->findBrokerAddress(),
                    $creator[0],
                    $creator[1]
                );
                $this->enrolmentRepository->assign($enrolment->id, $pollerId, $name);
                $pollers[] = ['id' => $pollerId, 'name' => $name, 'address' => $address];
                $pollerIds[] = $pollerId;
                $done[] = sprintf('enrolment %s: poller %s (#%d) created', $enrolment->id, $name, $pollerId);
            } catch (\Throwable $ex) {
                $this->error('Collector enrolment: poller not created', [
                    'enrolment' => $enrolment->id,
                    'exception' => $ex,
                ]);
                $done[] = sprintf('enrolment %s: poller not created: %s', $enrolment->id, $ex->getMessage());
            }
        }

        $nodes = $this->enrolmentRepository->findNodeStates();
        $revoked = [];
        foreach ($this->enrolmentRepository->findEnrolments() as $enrolment) {
            if (! $enrolment->isBound()) {
                continue;
            }
            $pollerId = (int) $enrolment->pollerId;
            if (! in_array($pollerId, $pollerIds, true)) {
                // Its poller was deleted (page, API or CLAPI): the collector loses its access.
                if (! isset($revoked[$pollerId])) {
                    $this->enrolmentRepository->deleteNode($pollerId);
                    $revoked[$pollerId] = true;
                    $done[] = sprintf('poller #%d deleted: node removed, credential revoked', $pollerId);
                }

                continue;
            }
            if ($enrolment->state !== CollectorEnrolment::STATE_ENROLLED) {
                continue;
            }
            $node = $nodes[$pollerId] ?? null;
            if ($node === null || ! $node['connected']) {
                continue;
            }
            if (! $node['configured']) {
                [$deployed, $output] = $this->deployer->deploy($pollerId);
                if (! $deployed) {
                    $this->error('Collector enrolment: first configuration failed', [
                        'poller_id' => $pollerId,
                        'output' => $output,
                    ]);
                    $done[] = sprintf('poller #%d: first configuration failed, retried next run', $pollerId);

                    continue;
                }
                $done[] = sprintf('poller #%d: first configuration sent', $pollerId);
            }
            $this->enrolmentRepository->complete($enrolment->id);
        }

        return $done;
    }

    /**
     * @throws CollectorEnrolmentException
     */
    private function assertEnabled(): void
    {
        foreach ([self::FEATURE_FLAG, ...self::REQUIRED_FLAGS] as $flag) {
            if (! $this->featureFlags->isEnabled($flag)) {
                throw CollectorEnrolmentException::featureDisabled($flag);
            }
        }
    }

    /**
     * Poller name and address of a fleet enrolment: what the machine reported, made unique.
     *
     * @param CollectorEnrolment $enrolment
     * @param list<array{id: int, name: string, address: string}> $pollers
     *
     * @return array{string, string}
     */
    private function chooseIdentity(CollectorEnrolment $enrolment, array $pollers): array
    {
        $base = substr(trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $enrolment->name), '-._'), 0, 32);
        if ($base === '') {
            $base = 'collector';
        }
        $taken = static fn (string $value, string $column): bool => array_filter(
            $pollers,
            static fn (array $poller): bool => strcasecmp($poller[$column], $value) === 0
        ) !== [];
        $name = $base;
        for ($rank = 2; $taken($name, 'name') || $taken($name, 'address'); $rank++) {
            $name = $base . '-' . $rank;
        }
        $address = $enrolment->address;
        if ($address === '' || $taken($address, 'address') || $taken($address, 'name')) {
            $address = $name;
        }

        return [$name, $address];
    }

    private function findPollerName(int $pollerId): ?string
    {
        foreach ($this->provisioner->findPollers() as $poller) {
            if ($poller['id'] === $pollerId) {
                return $poller['name'];
            }
        }

        return null;
    }

    private function option(string $name): ?string
    {
        foreach ($this->optionService->findSelectedOptions([$name]) as $option) {
            $value = trim((string) $option->getValue());
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return string agent gateway of gorgone-server, as the collectors reach it
     */
    private function findServerUrl(): string
    {
        $url = $this->option(self::OPTION_SERVER_URL);
        if ($url !== null) {
            return $url;
        }
        $address = $this->gorgoneConfiguration->getApiIpAddress();
        if ($address === null || $address === '') {
            throw CollectorEnrolmentException::invalid(sprintf(
                'Set the option %s: the address of gorgone-server is unknown',
                self::OPTION_SERVER_URL
            ));
        }

        return sprintf(
            '%s://%s:%d',
            $this->gorgoneConfiguration->isApiConnectionSecure() ? 'https' : 'http',
            $address,
            self::DEFAULT_AGENT_PORT
        );
    }

    /**
     * @return string address the collectors send their results to: the central broker input
     */
    private function findBrokerAddress(): string
    {
        $address = $this->option(self::OPTION_BROKER_ADDRESS) ?? $this->provisioner->findCentralAddress();
        if ($address === null || $address === '') {
            throw CollectorEnrolmentException::invalid(sprintf(
                'Set the option %s: the address of the central broker is unknown',
                self::OPTION_BROKER_ADDRESS
            ));
        }

        return $address;
    }

    /**
     * @param array<string, string> $environment
     * @param string|null $hostname
     */
    private function buildCommand(array $environment, ?string $hostname): string
    {
        $arguments = ['docker', 'run', '-d', '--restart', 'always'];
        if ($hostname !== null) {
            // The name of the poller, so that the machine reports it.
            array_push($arguments, '--name', $hostname, '--hostname', $hostname);
        }
        foreach ($environment as $variable => $value) {
            array_push($arguments, '-e', $variable . '=' . $value);
        }
        $arguments[] = $this->option(self::OPTION_IMAGE) ?? self::DEFAULT_IMAGE;

        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('#^[A-Za-z0-9_./:=@-]+$#', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            $arguments
        ));
    }
}
