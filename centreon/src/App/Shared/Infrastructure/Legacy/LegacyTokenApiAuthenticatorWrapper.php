<?php

declare(strict_types=1);

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
 */

namespace App\Shared\Infrastructure\Legacy;

use Security\TokenAPIAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Webmozart\Assert\Assert;

final readonly class LegacyTokenApiAuthenticatorWrapper implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    private TokenAPIAuthenticator $legacyAuthenticator;

    public function __construct(LegacyContainer $legacyContainer)
    {
        $legacyAuthenticator = $legacyContainer->get('security.provider.tokenapi');
        Assert::isInstanceOf($legacyAuthenticator, TokenAPIAuthenticator::class);

        $this->legacyAuthenticator = $legacyAuthenticator;
    }

    public function supports(Request $request): ?bool
    {
        return $this->legacyAuthenticator->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        return $this->legacyAuthenticator->authenticate($request);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->legacyAuthenticator->createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->legacyAuthenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->legacyAuthenticator->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->legacyAuthenticator->start($request, $authException);
    }
}
