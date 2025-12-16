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

use App\Security\Domain\Aggregate\Provider\Configuration;
use App\Security\Domain\Aggregate\Provider\OpenId\AuthenticationTypeEnum;
use App\Security\Domain\Aggregate\Provider\OpenId\AuthorizationEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\BaseUrl;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientId;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientSecret;
use App\Security\Domain\Aggregate\Provider\OpenId\ConnectionScope;
use App\Security\Domain\Aggregate\Provider\OpenId\EndSessionEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\IntrospectionTokenEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\LoginClaim;
use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\Provider\OpenId\RedirectUrl;
use App\Security\Domain\Aggregate\Provider\OpenId\TokenEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\Url;
use App\Security\Domain\Aggregate\Provider\OpenId\UserInfoEndpoint;
use App\Security\Domain\Aggregate\Provider\Provider;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalProviderRepository
 *
 * @phpstan-type OpenIdConfigurationType = array{
 *  base_url: string|null,
 *  redirect_url: string|null,
 *  client_id: string|null,
 *  client_secret: string|null,
 *  login_claim: string|null,
 *  token_endpoint: string|null,
 *  userinfo_endpoint: string|null,
 *  authentication_type: string,
 *  endsession_endpoint: string|null,
 *  authorization_endpoint: string|null,
 *  introspection_token_endpoint: string|null,
 *  connection_scopes: string[],
 *  should_verify_peer: bool|null
 *  }
 *
 * @implements TransformerInterface<RowTypeAlias, mixed>
 */
final readonly class DbalConfigurationTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): mixed
    {
        return match ($from['type']) {
            TokenIdpEnum::OpenId->value => $this->createOpenIdConfiguration($from),
            default => throw new \RuntimeException("Unsupported provider type '{$from['type']}'"),
        };
    }

    private function createOpenIdConfiguration(array $from): OpenIdConfiguration
    {
        /** @var OpenIdConfigurationType $jsonConfiguration */
        $jsonConfiguration = json_decode($from['custom_configuration'], true, flags: JSON_THROW_ON_ERROR);
        return new OpenIdConfiguration(
            baseUrl: $this->createValueObject(
                $jsonConfiguration['base_url'] ?? null,
                Url::class,
            ),
            redirectUrl: $this->createValueObject(
                $jsonConfiguration['redirect_url'] ?? null,
                Url::class,
            ),
            clientId: $this->createValueObject(
                $jsonConfiguration['client_id'] ?? null,
                ClientId::class,
            ),
            clientSecret: $this->createValueObject(
                $jsonConfiguration['client_secret'] ?? null,
                ClientSecret::class,
            ),
            loginClaim: $this->createValueObject(
                $jsonConfiguration['login_claim'] ?? null,
                LoginClaim::class,
            ),
            tokenEndpoint: $this->createValueObject(
                $jsonConfiguration['token_endpoint'] ?? null,
                Url::class,
            ),
            userInfoEndpoint: $this->createValueObject(
                $jsonConfiguration['userinfo_endpoint'] ?? null,
                Url::class,
            ),
            authenticationType: new AuthenticationTypeEnum($jsonConfiguration['authentication_type']),
            endSessionEndpoint: $this->createValueObject(
                $jsonConfiguration['endsession_endpoint'] ?? null,
                Url::class,
            ),
            authorizationEndpoint: $this->createValueObject(
                $jsonConfiguration['authorization_endpoint'] ?? null,
                Url::class,
            ),
            introspectionTokenEndpoint: $this->createValueObject(
                $jsonConfiguration['introspection_token_endpoint'] ?? null,
                Url::class,
            ),
            connectionScopes: array_map(
                static fn(string $scope): ConnectionScope => new ConnectionScope($scope),
                $jsonConfiguration['connection_scopes'] ?? []
            ),
            shouldVerifyPeer: (bool) ($jsonConfiguration['should_verify_peer'] ?? false),
            isActive: (bool) $from['is_active'],
            isForced: (bool) $from['is_forced'],
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $valueObjectClassString
     * @return T|null
     */
    private function createValueObject(string|null $value, string $valueObjectClassString): ?object
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return new $valueObjectClassString($value);

    }

}
