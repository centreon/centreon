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

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Provider\WebSSO\WebSSOConfiguration;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Security\Domain\Repository\CredentialRepository;
use App\Security\Domain\Repository\ProviderRepository;
use App\Security\Domain\Repository\TokenRepository;
use App\Security\Infrastructure\Idp\IdpFactory;
use App\Security\Infrastructure\Idp\WebSsoIdp;
use App\Security\Infrastructure\Legacy\LegacyAuthenticationServiceWrapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class WebSsoAuthenticator extends AbstractAuthenticator
{
    private WebSsoIdp $webSsoIdp = null;

    public function __construct(
        private readonly CredentialRepository $credentialRepository,
        private readonly LegacyAuthenticationServiceWrapper $authentication,
        private readonly TokenRepository $tokenRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly LoggerInterface $logger,
        private readonly IdpFactory $idpFactory,
    ) {
    }

    public function supports(Request $request): bool
    {
        $token = $this->tokenRepository->get($request->getSession()->getId());
        $isValidToken = $token !== null && $this->authentication->isValidToken($token->token);
        $this->webSsoIdp = $this->idpFactory->createByIdpEnum(TokenIdpEnum::WebSso);

        return ! $isValidToken
            && $this->webSsoIdp->getConfiguration()->isActive;
    }

    public function authenticate(Request $request): Passport
    {
        $configuration = $this->webSsoIdp->getConfiguration();

        if (! $this->ipIsAllowed($request, $configuration)) {
            throw new BadCredentialsException();
        }
        if (! in_array($configuration->loginHeaderAttribute->value, $_SERVER)) {
            throw new BadCredentialsException();
        }

        // TODO: Check Why creating a Redirect in the legacy authenticator
        // TODO: Check why we creating a session in the legacy authenticator
        return new SelfValidatingPassport(
            new UserBadge(
                $_SERVER[$configuration->loginHeaderAttribute->value],
                fn ($username) =>$this->getCredentialUser($username),
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): null
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): never
    {
        $this->logger->info('WebSSO authentication failed: {exceptionMessage}', [
            'exceptionMessage' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        throw $exception;
    }

    private function getCredentialUser(string $username): CredentialUser
    {
        if (! $credential = $this->credentialRepository->getByUsername($username)) {
            throw new BadCredentialsException();
        }

        $credential = new Credential(
            new CredentialIdentifier($username),
            $credential->userId ,
            true
        );

        return new CredentialUser($credential);
    }

    private function ipIsAllowed(Request $request, WebSSOConfiguration $configuration): bool
    {
        $clientIp = $request->getClientIp();
        if ($clientIp === null) {
            return false;
        }
        if (in_array($clientIp, $configuration->blacklistClientAddresses, true)) {
            return false;
        }

        if (! empty($configuration->trustedClientAddresses) && in_array($clientIp, $configuration->trustedClientAddresses, true)) {
            return false;
        }

        return true;
    }
}
