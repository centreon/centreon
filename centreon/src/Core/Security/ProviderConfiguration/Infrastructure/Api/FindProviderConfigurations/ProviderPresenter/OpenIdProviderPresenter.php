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

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\ProviderPresenter;

use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderResponse\{
    OpenIdProviderResponse
};
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OpenIdProviderPresenter implements ProviderPresenterInterface
{
    /**
     * @param UrlGeneratorInterface $router
     */
    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(mixed $response): bool
    {
        return is_a($response, OpenIdProviderResponse::class);
    }

    /**
     * @param OpenIdProviderResponse $response
     *
     * @return array<string,mixed>
     */
    public function present(mixed $response): array
    {
        $redirectUri = $response->redirectUrl !== null
            ? $response->redirectUrl . $this->router->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            : $this->router->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        return [
            'id' => $response->id,
            'type' => CustomConfiguration::TYPE,
            'name' => CustomConfiguration::NAME,
            'authentication_uri' => $this->buildAuthenticationUri(
                    $response->baseUrl,
                    $response->clientId,
                    $response->authorizationEndpoint,
                    $redirectUri,
                    $response->connectionScopes
                ),
            'is_active' => $response->isActive,
            'is_forced' => $response->isForced,
        ];
    }

    /**
     * Build authentication URI.
     *
     * @param string|null $baseUrl
     * @param string|null $clientId
     * @param string|null $authorizationEndpoint
     * @param string|null $redirectUri
     * @param string[]|null $connectionScopes
     *
     * @return string
     */
    private function buildAuthenticationUri(
        ?string $baseUrl,
        ?string $clientId,
        ?string $authorizationEndpoint,
        ?string $redirectUri,
        ?array $connectionScopes
    ): string {
        if ($baseUrl === null
            || $clientId === null
            || $authorizationEndpoint === null
            || $redirectUri === null
            || $connectionScopes === null
        ) {
            return '';
        }
        $authenticationUriParts = [
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => rtrim($redirectUri, '/'),
            'state' => uniqid(),
        ];
        $authorizationEndpointBase = parse_url($authorizationEndpoint, PHP_URL_PATH);
        $authorizationEndpointParts = parse_url($authorizationEndpoint, PHP_URL_QUERY);

        if ($authorizationEndpointBase === false || $authorizationEndpointParts === false) {
            throw new \ValueError(_('Unable to parse authorization url'));
        }

        $queryParams = http_build_query($authenticationUriParts);
        if ($authorizationEndpointParts !== null) {
            $queryParams .= '&' . $authorizationEndpointParts;
        }

        return $baseUrl . '/' . ltrim($authorizationEndpointBase ?? '', '/') . '?' . $queryParams
            . (! empty($connectionScopes) ? '&scope=' . implode('%20', $connectionScopes) : '');
    }
}
