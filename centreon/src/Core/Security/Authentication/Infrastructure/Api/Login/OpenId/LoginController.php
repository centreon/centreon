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

namespace Core\Security\Authentication\Infrastructure\Api\Login\OpenId;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ErrorAuthenticationConditionsResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\UnauthorizedResponse;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\UseCase\Login\ErrorAclConditionsResponse;
use Core\Security\Authentication\Application\UseCase\Login\Login;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Application\UseCase\Login\LoginResponse;
use Core\Security\Authentication\Application\UseCase\Login\PasswordExpiredResponse;
use Core\Security\Authentication\Domain\Exception\AuthenticationException;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class LoginController extends AbstractController
{
    use HttpUrlTrait;

    /**
     * @param Request $request
     * @param Login $useCase
     * @param LoginPresenter $presenter
     * @param SessionInterface $session
     *
     * @throws AuthenticationException
     * @throws ConflictingHeadersException
     *
     * @return View|Response|null
     */
    public function __invoke(
        Request $request,
        Login $useCase,
        LoginPresenter $presenter,
        SessionInterface $session
    ): null|View|Response {
        $loginRequest = LoginRequest::createForOpenId(
            $request->getClientIp() ?: '',
            $request->query->get('code', '')
        );

        $useCase($loginRequest, $presenter);

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
                    $this->getBaseUrl() . '/authentication-denied',
                );

            case $response instanceof LoginResponse:
                if ($response->redirectIsReact()) {
                    return View::createRedirect(
                        $this->getBaseUrl() . $response->getRedirectUri(),
                        headers: ['Set-Cookie' => 'PHPSESSID=' . $session->getId()]
                    );
                }

                return View::createRedirect(
                    $this->getBaseUrl() . '/login',
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
