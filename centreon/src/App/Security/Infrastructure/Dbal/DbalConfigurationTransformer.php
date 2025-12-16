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
use App\Security\Domain\Aggregate\Provider\OpenId\EndSessionEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\IntrospectionTokenEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\LoginClaim;
use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\Provider\OpenId\RedirectUrl;
use App\Security\Domain\Aggregate\Provider\OpenId\TokenEndpoint;
use App\Security\Domain\Aggregate\Provider\OpenId\UserInfoEndpoint;
use App\Security\Domain\Aggregate\Provider\Provider;
use App\Security\Domain\Aggregate\Provider\WebSSO\LoginHeaderAttribute;
use App\Security\Domain\Aggregate\Provider\WebSSO\PatternMatchingLogin;
use App\Security\Domain\Aggregate\Provider\WebSSO\PatternReplaceLogin;
use App\Security\Domain\Aggregate\Provider\WebSSO\WebSSOConfiguration;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalProviderRepository
 *
 * @phpstan-type WebSSOConfigurationType = array{
 *   pattern_replace_login: string|null,
 *   login_header_attribute: string,
 *   pattern_matching_login: string|null,
 *   trusted_client_addresses: string[],
 *   blacklist_client_addresses: string[]
 * }
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
 * @implements TransformerInterface<RowTypeAlias, Provider>
 */
final readonly class DbalConfigurationTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): Configuration
    {
        return match ($from['type']) {
            TokenIdpEnum::WebSso->value => $this->createWebSSOConfiguration($from),
            TokenIdpEnum::OpenId->value => $this->createOpenIdConfiguration($from),
        };
    }

    private function createWebSSOConfiguration(array $from): WebSSOConfiguration
    {
        /** @var WebSSOConfigurationType $jsonConfiguration */
        $jsonConfiguration = json_decode($from['custom_configuration'], true, flags: JSON_THROW_ON_ERROR);
        return new WebSSOConfiguration(
            patternReplaceLogin: $jsonConfiguration['pattern_replace_login'] !== null
                ? new PatternReplaceLogin($jsonConfiguration['pattern_replace_login'])
                : null,
            loginHeaderAttribute:  new LoginHeaderAttribute($jsonConfiguration['login_header_attribute']),
            patternMatchingLogin: $jsonConfiguration['pattern_matching_login'] !== null
                ? new PatternMatchingLogin($jsonConfiguration['pattern_matching_login'])
                : null,
            trustedClientAddresses: $jsonConfiguration['trusted_client_addresses'] ?? [],
            blacklistClientAddresses: $jsonConfiguration['blacklist_client_addresses'] ?? [],
            isActive: (bool) $from['is_active'],
            isForced: (bool) $from['is_forced'],
        );
    }

    private function createOpenIdConfiguration(array $from): OpenIdConfiguration
    {
        /** @var OpenIdConfigurationType $jsonConfiguration */
        $jsonConfiguration = json_decode($from['custom_configuration'], true, flags: JSON_THROW_ON_ERROR);
        return new OpenIdConfiguration(
            baseUrl: isset($jsonConfiguration['base_url'])
                ? new BaseUrl($jsonConfiguration['base_url'])
                : null,
            redirectUrl: isset($jsonConfiguration['redirect_url'])
                ? new RedirectUrl($jsonConfiguration['redirect_url'])
                : null,
            clientId: isset($jsonConfiguration['client_id'])
                ? new ClientId($jsonConfiguration['client_id'])
                : null,
            clientSecret: isset($jsonConfiguration['client_secret'])
                ? new ClientSecret($jsonConfiguration['client_secret'])
                : null,
            loginClaim: isset($jsonConfiguration['login_claim'])
                ? new LoginClaim($jsonConfiguration['login_claim'])
                : null,
            tokenEndpoint: isset($jsonConfiguration['token_endpoint'])
                ? new TokenEndpoint($jsonConfiguration['token_endpoint'])
                : null,
            userinfoEndpoint: isset($jsonConfiguration['userinfo_endpoint'])
                ? new UserInfoEndpoint($jsonConfiguration['userinfo_endpoint'])
                : null,
            authenticationType: AuthenticationTypeEnum::from($jsonConfiguration['authentication_type']),
            endsessionEndpoint: isset($jsonConfiguration['endsession_endpoint'])
                ? new EndSessionEndpoint($jsonConfiguration['endsession_endpoint'])
                : null,
            authorizationEndpoint: isset($jsonConfiguration['authorization_endpoint'])
                ? new AuthorizationEndpoint($jsonConfiguration['authorization_endpoint'])
                : null,
            introspectionTokenEndpoint: isset($jsonConfiguration['introspection_token_endpoint'])
                ? new IntrospectionTokenEndpoint($jsonConfiguration['introspection_token_endpoint'])
                : null,
            connectionScopes: $jsonConfiguration['connection_scopes'] ?? [],
            shouldVerifyPeer: (bool) ($jsonConfiguration['should_verify_peer'] ?? true),
            isActive: (bool) $from['is_active'],
            isForced: (bool) $from['is_forced'],
        );
    }

}
