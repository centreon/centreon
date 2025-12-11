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

use App\Security\Domain\Aggregate\Token;
use App\Security\Domain\Repository\TokenRepository;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenIdIdp implements IdpInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private TokenRepository $tokenRepository,
    ) {
    }

    public function refreshToken(Token $token): void
    {
        $refreshToken = $this->tokenRepository->getRefreshToken($token);

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

        $url = str_starts_with($configuration['token_endpoint'], '/')
            ? $configuration['base_url'].$configuration['token_endpoint']
            : $configuration['token_endpoint'];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken->token,
            'scope' => $configuration['scopes'] ? implode(' ', $configuration['scopes']) : null,
        ];

        if ($configuration['authentication_type'] === 'client_secret_basic') {
            $headers['Authorization'] = 'Basic '.base64_encode(
                $configuration['client_id'].':'.$configuration['client_secret'],
            );
        }

        if ($configuration['authentication_type'] === 'client_secret_post') {
            $body['client_id'] = $configuration['client_id'];
            $body['client_secret'] = $configuration['client_secret'];
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
            'verify_peer' => $configuration['verify_peer'],
        ]);

        try {
            /** @var array{access_token: string, expires_in: int, refresh_token?: string, refresh_expires_in?: int} $content */
            $content = $response->toArray();
        } catch (HttpExceptionInterface) {
            // TODO log and throw
        }
    }

    /**
     * @return array{
     *   scopes: array<string>,
     *   client_id: ?string,
     *   client_secret: ?string,
     *   authentication_type: string,
     *   base_url: string,
     *   token_endpoint: string,
     *   verify_peer: bool,
     * }
     */
    private function getConfiguration(): array
    {
        // TODO
        $configuration = [
            'scopes' => [],
            'client_id' => '',
            'client_secret' => '',
            'authentication_type' => '',
            'base_url' => '',
            'token_endpoint' => '',
            'verify_peer' => true,
        ];

        if (str_starts_with($configuration['client_id'], 'secret::')) {
            // TODO read from vault and update client_id
        }

        if (str_starts_with($configuration['client_secret'], 'secret::')) {
            // TODO read from vault and update client_secret
        }

        return $configuration;
    }
}
