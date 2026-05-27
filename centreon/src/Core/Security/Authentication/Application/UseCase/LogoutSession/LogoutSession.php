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

namespace Core\Security\Authentication\Application\UseCase\LogoutSession;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Domain\Exception\OpenIdException;
use Core\Security\Authentication\Domain\Exception\ProviderException;
use Core\Security\Authentication\Domain\Exception\SamlException;
use Core\Security\Authentication\Infrastructure\Provider\OpenId;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration as SamlCustomConfiguration;
use Symfony\Component\HttpFoundation\RequestStack;

class LogoutSession
{
    /**
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     * @param RequestStack $requestStack
     */
    public function __construct(
        private readonly WriteSessionRepositoryInterface $writeSessionRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param mixed $token
     * @param LogoutSessionPresenterInterface $presenter
     */
    public function __invoke(
        mixed $token,
        LogoutSessionPresenterInterface $presenter,
    ): void {
        if ($token === null || is_string($token) === false) {
            $presenter->setResponseStatus(new ErrorResponse(message: _('No session token provided')));

            return;
        }

        $session = $this->requestStack->getSession();
        $centreon = $session->get('centreon');
        $authType = $centreon->user->authType ?? null;
        // Captured before invalidation because the session is wiped below.
        $idToken = $session->get('openid_id_token') ?? '';

        // SAML: the LogoutRequest reads the SAML identifiers still held in session ($_SESSION['saml']),
        // so it must run BEFORE the local invalidation. With LOGOUT_FROM_CENTREON_AND_IDP it redirects to
        // the IdP (header + exit); the local session is then invalidated on the IdP callback (/saml/sls).
        if ($authType === Provider::SAML) {
            $this->logoutFromSaml();
        }

        try {
            $this->writeSessionRepository->invalidate();
        } catch (RepositoryException $e) {
            $presenter->setResponseStatus(
                new ErrorResponse(message: _('An error occurred during session logout'), exception: $e),
            );
        }

        // OpenID: idToken was captured above and logout() does not read the session, so we invalidate the
        // local session first, then redirect to the end-session endpoint (header + exit). OpenID has no
        // callback equivalent to /saml/sls, hence the local session must be wiped before redirecting.
        if ($authType === Provider::OPENID) {
            $this->logoutFromOpenId(is_string($idToken) ? $idToken : '');
        }
    }

    private function logoutFromSaml(): void
    {
        try {
            /** @var SAML $provider */
            $provider = $this->providerFactory->create(Provider::SAML);
            $configuration = $provider->getConfiguration();
            /** @var SamlCustomConfiguration $customConfiguration */
            $customConfiguration = $configuration->getCustomConfiguration();
            if (
                $configuration->isActive()
                && $customConfiguration->getLogoutFrom() === SamlCustomConfiguration::LOGOUT_FROM_CENTREON_AND_IDP
            ) {
                $provider->logout(); // The redirection to the IdP is done here (header + exit)
            }
        } catch (ProviderException|SamlException $e) {
            ExceptionLogger::create()->log(
                throwable: $e,
                context: ['provider' => Provider::SAML, 'action' => 'User-initiated SAML logout'],
            );
        }
    }

    private function logoutFromOpenId(string $idToken): void
    {
        try {
            /** @var OpenId $provider */
            $provider = $this->providerFactory->create(Provider::OPENID);
            if ($provider->getConfiguration()->isActive()) {
                // $stay = false so the user is redirected to the OIDC end-session endpoint (header + exit).
                $provider->logout($idToken, false);
            }
        } catch (ProviderException|OpenIdException $e) {
            ExceptionLogger::create()->log(
                throwable: $e,
                context: ['provider' => Provider::OPENID, 'action' => 'User-initiated OpenID logout'],
            );
        }
    }
}
