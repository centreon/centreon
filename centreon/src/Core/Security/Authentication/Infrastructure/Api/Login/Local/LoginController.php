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

namespace Core\Security\Authentication\Infrastructure\Api\Login\Local;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\UseCase\Login\Login;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class LoginController extends AbstractController
{
    use HttpUrlTrait;
    use LoggerTrait;

    /**
     * @param Request $request
     * @param Login $useCase
     * @param LoginPresenter $presenter
     * @param RequestStack $requestStack
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        Login $useCase,
        LoginPresenter $presenter,
        RequestStack $requestStack
    ): Response {
        /** @var string $content */
        $content = $request->getContent();
        /** @var array{login?: string, password?: string} $payload */
        $payload = json_decode($content, true);

        if ($referer = $request->headers->get('referer')) {
            $referer = parse_url($referer, PHP_URL_QUERY);
        }

        $loginRequest = LoginRequest::createForLocal(
            (string) ($payload['login'] ?? ''),
            (string) ($payload['password'] ?? ''),
            $request->getClientIp(),
            $referer ?: null
        );

        try {
            $useCase($loginRequest, $presenter);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

        $presenter->setResponseHeaders(
            ['Set-Cookie' => 'PHPSESSID=' . $requestStack->getSession()->getId()]
        );

        return $presenter->show();
    }
}
