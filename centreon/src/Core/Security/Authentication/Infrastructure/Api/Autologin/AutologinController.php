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

namespace Core\Security\Authentication\Infrastructure\Api\Autologin;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\UseCase\Autologin\Autologin;
use Core\Security\Authentication\Application\UseCase\Autologin\AutologinRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AutologinController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param Autologin $useCase
     * @param AutologinPresenter $presenter
     * @param SessionInterface $session
     *
     * @return object
     */
    public function __invoke(
        Request $request,
        Autologin $useCase,
        AutologinPresenter $presenter,
        SessionInterface $session
    ): object {

        parse_str($request->getQueryString(), $arguments);

        $autologinRequest = new AutologinRequest(
            (string) ($arguments['token'] ?? ''),
            (string) ($arguments['target'] ?? ''),
        );

        try {
            $useCase($autologinRequest, $presenter);
            $presenter->setResponseHeaders(
                ['Set-Cookie' => 'PHPSESSID=' . $session->getId()]
            );
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }


        return $presenter->show();
    }
}
