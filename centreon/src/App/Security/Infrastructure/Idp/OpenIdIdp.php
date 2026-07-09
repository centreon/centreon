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
use App\Security\Domain\Aggregate\Provider\OpenId\ConnectionScope;
use App\Security\Domain\Aggregate\Token;
use App\Security\Domain\Exception\TokenRefreshException;
use App\Security\Domain\Repository\OpenIdProviderRepository;
use App\Security\Domain\Repository\TokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenIdIdp implements IdpInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private TokenRepository $tokenRepository,
        private OpenIdProviderRepository $providerRepository,
        private LoggerInterface $authenticationLogger,
        private RequestStack $requestStack,
    ) {
    }

    public function refreshToken(Token $token): void
    {
        $refreshToken = $this->tokenRepository->getRefreshToken($token->token);

        if ($refreshToken->isExpired()) {
            throw new TokenRefreshException('Refresh token is expired.');
        }

        $result = $this->callRefreshTokenApi($refreshToken);
        $this->authenticationLogger->info('Token Refreshed',
            [
                ...$this->anonymizeContent($result),
                'datetime' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'ip_address' => $this->requestStack->getMainRequest()?->getClientIp(),
            ]
        );

        $token->token = $result['access_token'];
        $token->willExpireIn((int) $result['expires_in']);

        $this->tokenRepository->update($token);

        if (null === ($refreshTokenString = $result['refresh_token'] ?? null)) {
            return;
        }

        $expirationDelay = array_key_exists('refresh_expires_in', $result)
            ? (int) $result['refresh_expires_in']
            : ((int) $result['expires_in'] + 3600);

        $refreshToken->token = $refreshTokenString;
        $refreshToken->willExpireIn($expirationDelay);

        $this->tokenRepository->update($refreshToken);
    }

    /**
     * @return array<string, string>
     */
    private function callRefreshTokenApi(Token $refreshToken): array
    {
        $configuration = $this->providerRepository->getConfiguration();

        $url = str_starts_with($configuration->tokenEndpoint->value, '/')
            ? $configuration->baseUrl->value . $configuration->tokenEndpoint->value
            : $configuration->tokenEndpoint->value;

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken->token,
            'scope' => $configuration->connectionScopes === []
                ? null
                : implode(
                    ' ',
                    array_map(static fn (ConnectionScope $scope): string => $scope->value, $configuration->connectionScopes)
                ),
        ];

        switch ($configuration->authenticationType) {
            case AuthenticationTypeEnum::ClientSecretBasic:
                $headers['Authorization'] = 'Basic ' . base64_encode(
                    $configuration->clientId->value . ':' . $configuration->clientSecret->value
                );
                break;
            case AuthenticationTypeEnum::ClientSecretPost:
                $body['client_id'] = $configuration->clientId->value;
                $body['client_secret'] = $configuration->clientSecret->value;
                break;
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
            'verify_peer' => $configuration->shouldVerifyPeer,
        ]);

        try {
            /** @var array<string, string> */
            $responseAsArray = $response->toArray();

            return $responseAsArray;
        } catch (HttpExceptionInterface $e) {

            $errorDescription = ($response->toArray(false)['error_description'] ?? '');
            $this->authenticationLogger->error('OpenID token refresh failed: {content}', [
                'status_code' => $response->getStatusCode(),
                'content' => 'Refresh Token Request Error: ' . (is_string($errorDescription) ? $errorDescription : ''),
                'datetime' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'ip_address' => $this->requestStack->getMainRequest()?->getClientIp(),
                'exception' => $e,
            ]);

            throw new TokenRefreshException('Failed to refresh token via OpenID provider API.', $e->getCode(), previous: $e);
        }
    }

    /**
     * Anonymize sensitive content in the given data array.
     *
     * @param array<string, string> $data
     *
     * @return array<string, string>
     */
    private function anonymizeContent(array $data): array
    {
        if (isset($data['jti'])) {
            $data['jti'] = mb_substr($data['jti'], -10);
        }
        if (isset($data['access_token'])) {
            $data['access_token'] = mb_substr($data['access_token'], -10);
        }
        if (isset($data['refresh_token'])) {
            $data['refresh_token'] = mb_substr($data['refresh_token'], -10);
        }
        if (isset($data['id_token'])) {
            $data['id_token'] = mb_substr($data['id_token'], -10);
        }
        if (isset($data['provider_token'])) {
            $data['provider_token'] = mb_substr($data['provider_token'], -10);
        }

        return $data;
    }
}
