<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Authentication\Application\UseCase\Login;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This class aims to centralize the return login behaviour when initiated by a third party login form.
 *
 * It concerns :
 * - {@see https://mobile.centreon.com} progressive web app (PWA).
 *
 * This class is not 100% compliant about the Infrastructure and Application separation
 * but its nature is by definition a bit hacky.
 *
 * At the moment of its creation, we don't have a better idea to respond the need while
 * keeping track of the calls regarding this behaviour.
 */
final class ThirdPartyLoginForm
{
    private string $token = '';

    private ?bool $isActive = null;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Store the token used for building the final redirect Uri.
     * We need to forward the token to the original login page.
     *
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Used to get the return URL from the referer, before the auth.
     * The value is forwarded to the IDP to get it back after auth.
     *
     * @param Request $request
     */
    public function getReturnUrlBeforeAuth(Request $request): string
    {
        // Initiated by https://mobile.centreon.com
        if ('1' === $request->query->get('mobile')) {
            return (string) $request->headers->get('referer');
        }

        return '';
    }

    /**
     * Retrieve the redirectUrl after auth from the IDP information.
     * For SAML, this is the request parameter RelayState.
     */
    public function getReturnUrlAfterAuth(): string
    {
        if ('' === $this->token || ! $this->isActive()) {
            return '';
        }

        return $_REQUEST['RelayState'] . '#/callback?' . http_build_query(['token' => $this->token]);
    }

    /**
     * Tells whether the authentication was initiated by a third party login form in our context.
     */
    public function isActive(): bool
    {
        if (null !== $this->isActive) {
            return $this->isActive;
        }

        if (empty($returnTo = $_REQUEST['RelayState'] ?? null)) {
            return $this->isActive = false;
        }

        // We want to avoid possible loop redirects because the use of the RelayState in the SAML case is a bit hacky.
        $ourACS = $this->urlGenerator
            ->generate('centreon_application_authentication_login_saml', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->isActive = $returnTo !== $ourACS;
    }
}
