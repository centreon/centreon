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

namespace Core\Security\Authentication\Application\UseCase\Login;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This class exists to centralize the return to mobile behaviour.
 */
final class MobileLogin
{
    private string $token = '';

    private ?bool $isActive = null;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Store the token used for building the final redirect Uri.
     * We need to forward the token to the mobile page.
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
     * Tells whether the mobile authentication is a thing in the current context.
     */
    public function isActive(): bool
    {
        if (null !== $this->isActive) {
            return $this->isActive;
        }

        if (empty($returnTo = $_REQUEST['RelayState'] ?? null)) {
            return $this->isActive = false;
        }

        $ourACS = $this->urlGenerator->generate(
            'centreon_application_authentication_login_saml',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->isActive = $returnTo !== $ourACS;
    }
}
