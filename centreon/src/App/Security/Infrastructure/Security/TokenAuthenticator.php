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

namespace App\Security\Infrastructure\Security;

use App\Security\Domain\Exception\CredentialNotFoundException;
use App\Security\Domain\Exception\TokenNotFoundException;
use App\Security\Domain\Repository\CredentialRepository;
use App\Security\Domain\Repository\TokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class TokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly CredentialRepository $credentialRepository,
        private readonly TokenRepository $tokenRepository,
        private readonly LoggerInterface $tokenLogger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('X-AUTH-TOKEN');
    }

    public function authenticate(Request $request): Passport
    {
        if (! $token = $request->headers->get('X-AUTH-TOKEN')) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        return new SelfValidatingPassport(
            new UserBadge($token, $this->getCredentialUser(...)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): null
    {
        try {
            $apiToken = $this->tokenRepository->get($request->headers->get('X-AUTH-TOKEN') ?? '');
        } catch (TokenNotFoundException) {
            return null;
        }

        $this->tokenLogger->info('Token usage succeeded.', [
            'event' => 'usage',
            'status' => 'success',
            'datetime' => new \DateTimeImmutable(),
            'token' => mb_substr($apiToken->token, -10),
            'token_type' => 'api',
            'endpoint' => $request->getRequestUri(),
            'httpMethod' => $request->getMethod(),
            'ip_address' => $request->getClientIp(),
        ]);

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => strtr($exception->getMessageKey(), $exception->getMessageData())], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => _('Authentication Required')], Response::HTTP_UNAUTHORIZED);
    }

    private function getCredentialUser(string $token): CredentialUser
    {
        try {
            $credential = $this->credentialRepository->getByToken($token);
        } catch (CredentialNotFoundException) {
            throw new UserNotFoundException();
        }

        if (! $credential->active) {
            throw new DisabledException();
        }

        $this->extendTokenDuration($token);

        return new CredentialUser($credential);
    }

    private function extendTokenDuration(string $tokenString): void
    {
        try {
            $token = $this->tokenRepository->get($tokenString);
        } catch (TokenNotFoundException) {
            return;
        }

        if ($token->isExpired()) {
            throw new AuthenticationCredentialsNotFoundException('Token expired.');
        }

        if (! $token->auto) {
            return;
        }

        $token->willExpireIn($this->tokenRepository->getTokenExpirationShift());

        $this->tokenRepository->update($token);
    }
}
