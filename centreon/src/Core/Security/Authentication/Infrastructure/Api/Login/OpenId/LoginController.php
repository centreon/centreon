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
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;

final class LoginController extends AbstractController
{
    use HttpUrlTrait;

    /**
     * @param Request $request
     * @param Login $useCase
     * @param LoginPresenter $presenter
     *
     * @return View
     */
    public function __invoke(
        Request $request,
        Login $useCase,
        LoginPresenter $presenter,
    ): View {
        $loginRequest = LoginRequest::createForOpenId(
            $request->getClientIp() ?: '',
            (string) $request->query->get('code', '')
        );

        $useCase($loginRequest, $presenter);

        $response = $presenter->getResponseStatus() ?? $presenter->getPresentedData();

        return match (true) {
            $response instanceof PasswordExpiredResponse, $response instanceof UnauthorizedResponse, $response instanceof ErrorResponse => View::createRedirect(
                $this->getBaseUrl() . '/login?' . http_build_query([
                    'authenticationError' => $response->getMessage(),
                ]),
            ),
            $response instanceof ErrorAclConditionsResponse, $response instanceof ErrorAuthenticationConditionsResponse => View::createRedirect(
                $this->getBaseUrl() . '/authentication-denied',
            ),
            $response instanceof LoginResponse => View::createRedirect(
                $this->getBaseUrl() . $response->getRedirectUri(),
            ),
            default => View::createRedirect(
                $this->getBaseUrl() . '/login?' . http_build_query([
                    'authenticationError' => 'Unknown error',
                ]),
            ),
        };
    }
}
