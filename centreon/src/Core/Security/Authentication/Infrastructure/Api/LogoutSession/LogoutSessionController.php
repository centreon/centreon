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

namespace Core\Security\Authentication\Infrastructure\Api\LogoutSession;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSession;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSessionPresenterInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

final class LogoutSessionController extends AbstractController
{
    use HttpUrlTrait;

    /**
     * @param LogoutSession $useCase
     * @param Request $request
     * @param LogoutSessionPresenterInterface $presenter
     *
     * @throws BadRequestException
     * @return object
     */
    public function __invoke(
        LogoutSession $useCase,
        Request $request,
        LogoutSessionPresenterInterface $presenter,
    ): object {
        $basePath = mb_ltrim($request->getBasePath(), '/');
        $sessionName = session_name() ?: 'PHPSESSID';

        $sessionId = null;

        if ($basePath !== '') {
            $sessionId = $request->cookies->get($sessionName . '_' . $basePath);
        }

        if ($sessionId === null) {
            $sessionId = $request->cookies->get($sessionName);
        }

        $useCase($sessionId, $presenter);

        // TODO: response is not used, should we return a response ? (we return a redirection to login page)
        $response = $presenter->getResponseStatus();
        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                ExceptionLogger::create()->log($response->getException());
            }
        }

        return $this->redirect($this->getBaseUrl() . '/login');
    }
}
