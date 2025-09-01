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

use Core\Common\Domain\Exception\RepositoryException;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\OpenId;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Exception;
use OneLogin\Saml2\Error;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class WriteSessionRepository implements WriteSessionRepositoryInterface
{

    /**
     * @param RequestStack $requestStack
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     */
    public function __construct(
        private RequestStack $requestStack,
        private WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private ProviderAuthenticationFactoryInterface $providerFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invalidate(?callable $callback = null): void
    {
        $session = $this->requestStack->getSession();
        $idToken = $session->get('openid_id_token') ?? '';
        $sessionId = $session->getId();
        $this->writeSessionTokenRepository->deleteSession($sessionId);
        $centreon = $session->get('centreon');

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
                try {
                    $provider->logout();
                } catch (Error $e) {
                    throw new RepositoryException(
                        message: 'SAML logout failed: ' . $e->getMessage(),
                        context: ['user_id' => $centreon->user->user_id],
                        previous: $e
                    );
                }
            }
        }

        if ($centreon && $centreon->user->authType === Provider::OPENID) {
            /** @var OpenId $provider */
            $provider = $this->providerFactory->create(Provider::OPENID);
            $configuration = $provider->getConfiguration();
            $isLogin = $this->requestStack->getSession()->get('isLogin') ?? false;
            if ($configuration->isActive()) {
                try {
                    $provider->logout($idToken, $isLogin);
                } catch (Exception $e) {
                    throw new RepositoryException(
                        message: 'OpenID logout failed: ' . $e->getMessage(),
                        context: ['user_id' => $centreon->user->user_id],
                        previous: $e
                    );
                }
            }
        }

        if ($callback !== null) {
            try {
                $callback();
            } catch (\Throwable $e) {
                throw new RepositoryException(
                    message: 'An error occurred while executing the invalidate callback: ' . $e->getMessage(),
                    context: ['user_id' => $centreon->user->user_id],
                    previous: $e
                );
            }
        }

        $session->invalidate();
    }

    /**
     * @inheritDoc
     */
    public function start(\Centreon $legacySession): bool
    {
        if ($this->requestStack->getSession()->isStarted()) {
            return true;
        }

        $this->requestStack->getSession()->start();
        $this->requestStack->getSession()->set('centreon', $legacySession);
        $this->requestStack->getSession()->set('isLogin', true);
        $_SESSION['centreon'] = $legacySession;

        $isSessionStarted = $this->requestStack->getSession()->isStarted();
        if ($isSessionStarted === false) {
            try {
                $this->invalidate();
            } catch (RepositoryException $e) {
                throw new RepositoryException(
                    message: 'An error occurred while invalidating the session after a failed start: ' . $e->getMessage(),
                    context: ['user_id'=>$legacySession->user->user_id],
                    previous: $e
                );
            }
        }

        return $isSessionStarted;
    }
}
