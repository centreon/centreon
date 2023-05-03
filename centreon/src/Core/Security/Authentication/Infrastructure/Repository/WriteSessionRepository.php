<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Authentication\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class WriteSessionRepository implements WriteSessionRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param SessionInterface $session
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param WriteTokenRepositoryInterface $writeTokenRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->writeTokenRepository->deleteExpiredSecurityTokens();
        $this->writeSessionTokenRepository->deleteSession($this->session->getId());
        $centreon = $this->session->get("centreon");
        $this->session->invalidate();

        if ($centreon && $centreon->user->authType === Provider::SAML) {
            /** @var SAML $provider */
            $provider = $this->providerFactory->create(Provider::SAML);
            $configuration = $provider->getConfiguration();
            $customConfiguration = $configuration->getCustomConfiguration();
            if (
                $configuration->isActive() &&
                $customConfiguration->getLogoutFrom() === CustomConfiguration::LOGOUT_FROM_CENTREON_AND_IDP
            ) {
                $this->info('Logout from Centreon and SAML IDP...');
                $provider->logout(); // The redirection is done here by the IDP
            }
        }
    }

    /**
     * Start a session (included the legacy session)
     *
     * @param \Centreon $legacySession
     * @return bool
     */
    public function start(\Centreon $legacySession): bool
    {
        if ($this->session->isStarted()) {
            return true;
        }

        $this->info('[AUTHENTICATE] Starting Centreon Session');
        $this->session->start();
        $this->session->set('centreon', $legacySession);
        $_SESSION['centreon'] = $legacySession;

        $isSessionStarted = $this->session->isStarted();
        if ($isSessionStarted === false) {
            $this->invalidate();
        }

        return $isSessionStarted;
    }
}
