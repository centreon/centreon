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

namespace Core\Notification\Infrastructure\API\FindNotifications;

use Symfony\Component\HttpFoundation\Response;
use Centreon\Application\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Core\Notification\Application\UseCase\FindNotifications\FindNotifications;
use Core\Notification\Infrastructure\API\FindNotifications\FindNotificationsPresenter;

final class FindNotificationsController extends AbstractController
{
    /**
     * @param FindNotifications $useCase
     * @param FindNotificationsPresenter $presenter
     *
     * @return Response
     *
     * @throws AccessDeniedException
     */
    public function __invoke(FindNotifications $useCase, FindNotificationsPresenter $presenter): Response
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($presenter);
        return $presenter->show();
    }
}
