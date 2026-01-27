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

namespace App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Provider\OpenId\AbsoluteUrl;
use App\Security\Domain\Aggregate\Provider\OpenId\AuthenticationTypeEnum;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientId;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientSecret;
use App\Security\Domain\Aggregate\Provider\OpenId\ConnectionScope;
use App\Security\Domain\Aggregate\Provider\OpenId\LoginClaim;
use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\Provider\OpenId\Url;
use App\Shared\Domain\VaultInterface;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalOpenIdProviderRepository
 *
 * @phpstan-type OpenIdConfigurationTypeAlias = array{
 *  base_url: string|null,
 *  redirect_url: string|null,
 *  client_id: string,
 *  client_secret: string,
 *  login_claim: string,
 *  token_endpoint: string,
 *  userinfo_endpoint: string|null,
 *  authentication_type: string,
 *  endsession_endpoint: string|null,
 *  authorization_endpoint: string,
 *  introspection_token_endpoint: string|null,
 *  connection_scopes: string[],
 *  should_verify_peer: bool|null
 *  }
 *
 * @implements TransformerInterface<RowTypeAlias, OpenIdConfiguration>
 */
final readonly class DbalOpenIdConfigurationTransformer implements TransformerInterface
{
    public function __construct(private VaultInterface $vault)
    {
    }

    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): OpenIdConfiguration
    {
        /** @var OpenIdConfigurationTypeAlias $jsonConfiguration */
        $jsonConfiguration = json_decode($from['custom_configuration'], true, flags: JSON_THROW_ON_ERROR);
        $requiredKeys = ['base_url', 'client_id', 'client_secret', 'login_claim', 'token_endpoint', 'authorization_endpoint'];
        foreach ($requiredKeys as $key) {
            if (empty($jsonConfiguration[$key])) {
                throw new \InvalidArgumentException("Required OpenIdConfiguration parameter '{$key}' is null or empty");
            }
        }
        if (str_starts_with($jsonConfiguration['client_secret'], 'secret::')) {
            /**
             * @var array{
             *      _OPENID_CLIENT_ID: string,
             *      _OPENID_CLIENT_SECRET: string
             * } $vaultData
             */
            $vaultData = $this->vault->read($jsonConfiguration['client_secret']);
            $jsonConfiguration['client_secret'] = $vaultData[VaultInterface::OPENID_CLIENT_SECRET_KEY];
            $jsonConfiguration['client_id'] = $vaultData[VaultInterface::OPENID_CLIENT_ID_KEY];
        }

        return new OpenIdConfiguration(
            baseUrl: $this->createRequiredValueObject(
                $jsonConfiguration['base_url'],
                AbsoluteUrl::class,
                'base_url',
            ),
            redirectUrl: $this->createValueObject(
                $jsonConfiguration['redirect_url'] ?? null,
                Url::class,
            ),
            clientId: $this->createRequiredValueObject(
                $jsonConfiguration['client_id'],
                ClientId::class,
                'client_id',
            ),
            clientSecret: $this->createRequiredValueObject(
                $jsonConfiguration['client_secret'],
                ClientSecret::class,
                'client_secret',
            ),
            loginClaim: $this->createRequiredValueObject(
                $jsonConfiguration['login_claim'],
                LoginClaim::class,
                'login_claim',
            ),
            tokenEndpoint: $this->createRequiredValueObject(
                $jsonConfiguration['token_endpoint'],
                Url::class,
                'token_endpoint',
            ),
            userInfoEndpoint: $this->createValueObject(
                $jsonConfiguration['userinfo_endpoint'] ?? null,
                Url::class,
            ),
            authenticationType: AuthenticationTypeEnum::from($jsonConfiguration['authentication_type']),
            endSessionEndpoint: $this->createValueObject(
                $jsonConfiguration['endsession_endpoint'] ?? null,
                Url::class,
            ),
            authorizationEndpoint: $this->createRequiredValueObject(
                $jsonConfiguration['authorization_endpoint'],
                Url::class,
                'authorization_endpoint',
            ),
            introspectionTokenEndpoint: $this->createValueObject(
                $jsonConfiguration['introspection_token_endpoint'] ?? null,
                Url::class,
            ),
            connectionScopes: array_map(
                static fn (string $scope): ConnectionScope => new ConnectionScope($scope),
                $jsonConfiguration['connection_scopes'] ?? []
            ),
            shouldVerifyPeer: (bool) ($jsonConfiguration['should_verify_peer'] ?? false),
            isActive: (bool) $from['is_active'],
            isForced: (bool) $from['is_forced'],
        );
    }

    /**
     * Create a required value object (non-nullable).
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @throws \InvalidArgumentException When the required value is null or empty
     *
     * @return T
     */
    private function createRequiredValueObject(mixed $value, string $className, string $parameterName): object
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException("Required OpenIdConfiguration parameter '{$parameterName}' is null or empty");
        }

        return new $className($value);
    }

    /**
     * @template T of object
     * @param class-string<T> $valueObjectClassString
     * @return ($value is null ? null : T)
     */
    private function createValueObject(string|null $value, string $valueObjectClassString): ?object
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return new $valueObjectClassString($value);
    }
}
