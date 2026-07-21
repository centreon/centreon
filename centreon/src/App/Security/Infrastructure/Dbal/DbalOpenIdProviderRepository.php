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

namespace App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Security\Domain\Repository\OpenIdProviderRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *  id: int,
 *  type: string,
 *  custom_configuration: string,
 *  is_active: bool,
 *  is_forced: bool
 * }
 */
final readonly class DbalOpenIdProviderRepository extends DbalRepository implements OpenIdProviderRepository
{
    public const TABLE_NAME = 'provider_configuration';

    /**
     * @param TransformerInterface<RowTypeAlias, OpenIdConfiguration> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalOpenIdConfigurationTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function getConfiguration(): OpenIdConfiguration
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('id, type, custom_configuration, is_active, is_forced')
            ->from(self::TABLE_NAME)
            ->where('type = :tokenIdp')
            ->setParameter('tokenIdp', TokenIdpEnum::OpenId->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new \RuntimeException('Provider Configuration with token IDP ' . TokenIdpEnum::OpenId->value . ' does not exist.');
        }

        return $this->transformer->transform($row);
    }

    public function delete(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->where('type = :tokenIdp')
            ->setParameter('tokenIdp', TokenIdpEnum::OpenId->value);

        $qb->executeStatement();
    }

    public function update(OpenIdConfiguration $configuration): void
    {
        $qb = $this->connection->createQueryBuilder();

        /**
         * This method is only used in tests ATM, when using in a real context,
         * make sure to handle client_secret properly for vault cases and not saving it as plain text if a vault is active.
         */
        $customConfiguration = [
            'base_url' => $configuration->baseUrl->value,
            'redirect_url' => $configuration->redirectUrl?->value,
            'client_id' => $configuration->clientId->value,
            'client_secret' => $configuration->clientSecret->value,
            'login_claim' => $configuration->loginClaim->value,
            'token_endpoint' => $configuration->tokenEndpoint->value,
            'userinfo_endpoint' => $configuration->userInfoEndpoint?->value,
            'authentication_type' => $configuration->authenticationType->value,
            'endsession_endpoint' => $configuration->endSessionEndpoint?->value,
            'authorization_endpoint' => $configuration->authorizationEndpoint->value,
            'introspection_token_endpoint' => $configuration->introspectionTokenEndpoint?->value,
            'connection_scopes' => array_map(fn ($scope): string => $scope->value, $configuration->connectionScopes),
            'should_verify_peer' => $configuration->shouldVerifyPeer,
        ];
        $qb->update(self::TABLE_NAME)
            ->set('custom_configuration', ':custom_configuration')
            ->set('is_active', ':is_active')
            ->set('is_forced', ':is_forced')
            ->where('type = :type')
            ->setParameter('custom_configuration', json_encode($customConfiguration, JSON_THROW_ON_ERROR))
            ->setParameter('is_active', (int) $configuration->isActive)
            ->setParameter('is_forced', (int) $configuration->isForced)
            ->setParameter('type', TokenIdpEnum::OpenId->value);

        $qb->executeStatement();
    }
}
