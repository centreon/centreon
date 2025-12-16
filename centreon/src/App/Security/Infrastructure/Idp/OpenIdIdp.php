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

namespace App\Security\Infrastructure\Idp;

use App\Security\Domain\Aggregate\Provider\OpenId\AuthenticationTypeEnum;
use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\Token;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Security\Domain\Repository\ProviderRepository;
use App\Security\Domain\Repository\TokenRepository;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenIdIdp implements IdpInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private TokenRepository $tokenRepository,
        private ProviderRepository $providerRepository,
    ) {
    }

    public function refreshToken(Token $token): void
    {
        $refreshToken = $this->tokenRepository->getRefreshToken($token->token);

        if ($refreshToken->isExpired()) {
            throw new \RuntimeException('Refresh token is expired.');
        }

        $result = $this->callRefreshTokenApi($refreshToken);

        // TODO log ok

        $token->token = $result['access_token'];
        $token->willExpireIn($result['expires_in']);

        $this->tokenRepository->update($token);

        if (null === ($refreshTokenString = $result['refresh_token'] ?? null)) {
            return;
        }

        $expirationDelay = array_key_exists('refresh_expires_in', $result)
            ? $result['refresh_expires_in']
            : ($result['expires_in'] + 3600);

        $refreshToken->token = $refreshTokenString;
        $refreshToken->willExpireIn($expirationDelay);

        $this->tokenRepository->update($refreshToken);
    }

    /**
     * @return array{access_token: string, expires_in: int, refresh_token?: string, refresh_expires_in?: int}
     */
    private function callRefreshTokenApi(Token $refreshToken): array
    {
        $configuration = $this->getConfiguration();

        $url = str_starts_with($configuration->tokenEndpoint->value, '/')
            ? $configuration->baseUrl->value . $configuration->tokenEndpoint->value
            : $configuration->tokenEndpoint->value;

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken->token,
            'scope' => ! empty($configuration->connectionScopes) ? implode(' ', $configuration->connectionScopes) : null,
        ];

        switch ($configuration->authenticationType) {
            case AuthenticationTypeEnum::ClientSecretBasic:
                $headers['Authorization'] = 'Basic ' . base64_encode(
                    $configuration->clientId . ':' . $configuration->clientSecret
                );
                break;

            case AuthenticationTypeEnum::ClientSecretPost:
                $body['client_id'] = $configuration->clientId;
                $body['client_secret'] = $configuration->clientSecret;
                break;
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
            'verify_peer' => $configuration->shouldVerifyPeer,
        ]);

        try {
            /** @var array{access_token: string, expires_in: int, refresh_token?: string, refresh_expires_in?: int} $content */
            return $response->toArray();
        } catch (HttpExceptionInterface) {
            // TODO log and throw
        }
    }

    private function getConfiguration(): OpenIdConfiguration
    {
        $configuration = $this->providerRepository->getConfigurationByTokenIdp(TokenIdpEnum::OpenId);

        if (str_starts_with($configuration['client_id'], 'secret::')) {
            // TODO read from vault and update client_id
        }

        if (str_starts_with($configuration['client_secret'], 'secret::')) {
            // TODO read from vault and update client_secret
        }

        return $configuration;
    }
}
