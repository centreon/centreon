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

namespace Core\AgentConfiguration\Application\Validation;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfigurationRequest;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\JwtToken;

/**
 * @phpstan-import-type _CmaParameters from CmaConfigurationParameters
 */
class CmaValidator implements TypeValidatorInterface
{
    public function __construct(
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadTokenRepositoryInterface $tokenRepository,
        private readonly ContactInterface $user,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return Type::CMA === $type;
    }

    /**
     * @inheritDoc
     */
    public function validateParametersOrFail(AddAgentConfigurationRequest|UpdateAgentConfigurationRequest $request): void
    {
        /** @var _CmaParameters $configuration */
        $configuration = $request->configuration;

        $this->validateAgentInitiatedConnection($configuration, $request->connectionMode);
        $this->validatePollerInitiatedConnection($configuration, $request->connectionMode);
    }

    /**
     * @param _CmaParameters $configuration
     * @param ConnectionModeEnum $connectionMode
     */
    private function validateAgentInitiatedConnection(array $configuration, ConnectionModeEnum $connectionMode): void
    {
        if ($configuration['agent_initiated'] === false) {
            return;
        }

        if ($connectionMode !== ConnectionModeEnum::NO_TLS) {
            $this->validateFilename(
                'configuration.otel_public_certificate',
                $configuration['otel_public_certificate'],
                true
            );
            $this->validateFilename(
                'configuration.otel_ca_certificate',
                $configuration['otel_ca_certificate'],
                true
            );
            $this->validateFilename(
                'configuration.otel_private_key',
                $configuration['otel_private_key'],
                false
            );

            if ($configuration['tokens'] === []) {
                throw AgentConfigurationException::tokensAreMandatory();
            }
        }
        $this->validateTokens($configuration['tokens']);
    }

    /**
     * @param _CmaParameters $configuration
     * @param ConnectionModeEnum $connectionMode
     */
    private function validatePollerInitiatedConnection(array $configuration, ConnectionModeEnum $connectionMode): void
    {
        if ($configuration['poller_initiated'] === false) {
            return;
        }

        foreach ($configuration['hosts'] as $host) {
            $this->validateFilename('configuration.hosts[].poller_ca_certificate', $host['poller_ca_certificate'], true);

            if (! $this->readHostRepository->exists(hostId: $host['id'])) {
                throw AgentConfigurationException::invalidHostId($host['id']);
            }

            if ($connectionMode !== ConnectionModeEnum::NO_TLS && $host['token'] === null) {
                throw AgentConfigurationException::tokensAreMandatory();
            }

            if ($host['token'] !== null) {
                $this->validateTokens([$host['token']]);
            }
        }
    }

    /**
     * @param string $name
     * @param ?string $value
     * @param bool $isCertificate (default true)
     *
     * @throws AgentConfigurationException
     */
    private function validateFilename(string $name, ?string $value, bool $isCertificate = true): void
    {
        $pattern = $isCertificate
            ? '/\.\/|\.\.\/|\/\/|^(?!.*\.(cer|crt)$).+$/'
            : '/\.\/|\.\.\/|\/\/|^(?!.*\.key$).+$/';

        if ($value !== null && preg_match($pattern, $value)) {
            throw AgentConfigurationException::invalidFilename($name, (string) $value);
        }
    }

    /**
     * @param array<array{name:string,creator_id:int}> $tokens
     *
     * @throws AgentConfigurationException
     */
    private function validateTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (! $this->user->isAdmin() && $token['creator_id'] !== $this->user->getId() && ! $this->user->hasRole('ROLE_MANAGE_TOKENS')) {
                throw AgentConfigurationException::invalidToken($token['name'], $token['creator_id']);
            }
            $tokenObj = $this->tokenRepository->findByNameAndUserId($token['name'], $token['creator_id']);
            if (
                $tokenObj === null
                || ! $tokenObj instanceOf JwtToken
                || $tokenObj->isRevoked()
                || ($tokenObj->getExpirationDate() !== null && $tokenObj->getExpirationDate() < new \DateTimeImmutable())
            ) {
                throw AgentConfigurationException::invalidToken($token['name'], $token['creator_id']);
            }
        }
    }
}
