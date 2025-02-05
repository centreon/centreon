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

namespace Core\Security\Authentication\Infrastructure\Api\Login\SAML;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\{ErrorAuthenticationConditionsResponse, ErrorResponse, UnauthorizedResponse};
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\UseCase\Login\{ErrorAclConditionsResponse, Login, LoginRequest, LoginResponse, PasswordExpiredResponse, ThirdPartyLoginForm};
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CallbackController extends AbstractController
{
    use HttpUrlTrait;

    /**
     * @param Request $request
     * @param Login $useCase
     * @param CallbackPresenter $presenter
     * @param RequestStack $requestStack
     * @param ThirdPartyLoginForm $thirdPartyLoginForm
     *
     * @return View
     */
    public function __invoke(
        Request $request,
        Login $useCase,
        CallbackPresenter $presenter,
        RequestStack $requestStack,
        ThirdPartyLoginForm $thirdPartyLoginForm,
    ): View {
        $samlLoginRequest = LoginRequest::createForSAML((string) $request->getClientIp());

        $useCase($samlLoginRequest, $presenter);

        $response = $presenter->getResponseStatus() ?? $presenter->getPresentedData();

        switch (true) {
            case $response instanceof PasswordExpiredResponse:
            case $response instanceof UnauthorizedResponse:
            case $response instanceof ErrorResponse:
                return View::createRedirect(
                    $this->getBaseUrl() . '/login?' . http_build_query([
                        'authenticationError' => $response->getMessage(),
                    ]),
                );

            case $response instanceof ErrorAclConditionsResponse:
            case $response instanceof ErrorAuthenticationConditionsResponse:
                return View::createRedirect(
                    $this->getBaseUrl() . '/authentication-denied'
                );

            case $response instanceof LoginResponse:
                if ($redirectToThirdPartyLoginForm = $thirdPartyLoginForm->getReturnUrlAfterAuth()) {
                    return View::createRedirect($redirectToThirdPartyLoginForm);
                }

                if ($response->redirectIsReact()) {
                    return View::createRedirect(
                        $this->getBaseUrl() . $response->getRedirectUri(),
                        headers: ['Set-Cookie' => 'PHPSESSID=' . $requestStack->getSession()->getId()]
                    );
                }

                return View::createRedirect(
                    $this->getBaseUrl() . $response->getRedirectUri(),
                    headers: ['Set-Cookie' => 'REDIRECT_URI=' . $this->getBaseUrl() . $response->getRedirectUri() . ';Max-Age=10']
                );

            default:
                return View::createRedirect(
                    $this->getBaseUrl() . '/login?' . http_build_query([
                        'authenticationError' => 'Unknown error',
                    ]),
                );
        }
    }
}
