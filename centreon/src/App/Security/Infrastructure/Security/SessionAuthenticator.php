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
use App\Security\Domain\Exception\TokenRefreshException;
use App\Security\Domain\Exception\TokenRefreshUnavailableException;
use App\Security\Domain\Repository\CredentialRepository;
use App\Security\Domain\Repository\TokenRepository;
use App\Security\Infrastructure\Idp\IdpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class SessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly CredentialRepository $credentialRepository,
        private readonly IdpFactory $idpFactory,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Cookie') && $request->getSession()->getId();
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(
            new UserBadge($request->getSession()->getId(), $this->getCredentialUser(...)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): null
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => strtr($exception->getMessageKey(), $exception->getMessageData())], Response::HTTP_UNAUTHORIZED);
    }

    private function getCredentialUser(string $sessionId): CredentialUser
    {
        try {
            $token = $this->tokenRepository->get($sessionId);
        } catch (TokenNotFoundException) {
            throw new BadCredentialsException();
        }

        if ($token->isExpired()) {
            try {
                $this->idpFactory->create($token)->refreshToken($token);
            } catch (TokenRefreshException|TokenRefreshUnavailableException) {
                throw new BadCredentialsException();
            }
        }

        try {
            $credential = $this->credentialRepository->getBySession($sessionId);
        } catch (CredentialNotFoundException) {
            throw new UserNotFoundException();
        }

        if (! $credential->active) {
            throw new DisabledException();
        }

        return new CredentialUser($credential);
    }
}
