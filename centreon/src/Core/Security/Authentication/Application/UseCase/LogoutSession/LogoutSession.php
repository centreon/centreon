<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Security\Authentication\Application\UseCase\LogoutSession;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use OneLogin\Saml2\Error;
use Symfony\Component\HttpFoundation\RequestStack;

class LogoutSession
{
    use LoggerTrait;

    /**
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     * @param WriteTokenRepositoryInterface $writeTokenRepository
     * @param ReadTokenRepositoryInterface $readTokenRepository
     */
    public function __construct(
        private readonly WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private readonly WriteSessionRepositoryInterface $writeSessionRepository,
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory,
        private readonly RequestStack $requestStack
    )
    {
    }

    /**
     * @param mixed $token
     * @param LogoutSessionPresenterInterface $presenter
     * @throws Error
     */
    public function __invoke(
        mixed $token,
        LogoutSessionPresenterInterface $presenter,
    ): void
    {
        $this->info('Processing session logout...');

        if ($token === null || is_string($token) === false) {
            $this->debug('Try to logout without token');
            $presenter->setResponseStatus(new ErrorResponse(_('No session token provided')));
            return;
        }

        $this->deleteTokens();

        /** @var SAML $provider */
        $provider = $this->providerFactory->create(Provider::SAML);
        $configuration = $provider->getConfiguration();
        $customConfiguration = $configuration->getCustomConfiguration();
        if (
            $configuration->isActive() &&
            $customConfiguration->getLogoutFrom() === CustomConfiguration::LOGOUT_FROM_CENTREON_AND_IDP
        ) {
            $this->info('Logout from Centreon and SAML IDP...');
            $provider->logout();
        }

        $presenter->setResponseStatus(new NoContentResponse());
    }

    /**
     * @return void
     */
    private function deleteTokens(): void
    {
        $sessionId = $this->requestStack->getSession()->getId();
        $this->writeTokenRepository->deleteExpiredSecurityTokens();
        $this->writeSessionTokenRepository->deleteSession($sessionId);
        $this->writeSessionRepository->invalidate();
    }
}
