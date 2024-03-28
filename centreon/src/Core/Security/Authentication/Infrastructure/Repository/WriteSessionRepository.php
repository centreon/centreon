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

namespace Core\Security\Authentication\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Symfony\Component\HttpFoundation\RequestStack;

class WriteSessionRepository implements WriteSessionRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param RequestStack $requestStack
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->writeSessionTokenRepository->deleteSession($this->requestStack->getSession()->getId());
        $centreon = $this->requestStack->getSession()->get('centreon');
        $this->requestStack->getSession()->invalidate();

        if ($centreon && $centreon->user->authType === Provider::SAML) {
            /** @var SAML $provider */
            $provider = $this->providerFactory->create(Provider::SAML);
            $configuration = $provider->getConfiguration();
            /** @var CustomConfiguration $customConfiguration */
            $customConfiguration = $configuration->getCustomConfiguration();
            if (
                $configuration->isActive()
                && $customConfiguration->getLogoutFrom() === CustomConfiguration::LOGOUT_FROM_CENTREON_AND_IDP
            ) {
                $this->info('Logout from Centreon and SAML IDP...');
                $provider->logout(); // The redirection is done here by the IDP
            }
        }
    }

    /**
     * Start a session (included the legacy session).
     *
     * @param \Centreon $legacySession
     *
     * @return bool
     */
    public function start(\Centreon $legacySession): bool
    {
        if ($this->requestStack->getSession()->isStarted()) {
            return true;
        }

        $this->info('[AUTHENTICATE] Starting Centreon Session');
        $this->requestStack->getSession()->start();
        $this->requestStack->getSession()->set('centreon', $legacySession);
        $_SESSION['centreon'] = $legacySession;

        $isSessionStarted = $this->requestStack->getSession()->isStarted();
        if ($isSessionStarted === false) {
            $this->invalidate();
        }

        return $isSessionStarted;
    }
}
