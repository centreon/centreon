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

namespace Core\Notification\Infrastructure\API\FindNotifiableResources;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Notification\Application\UseCase\FindNotifiableResources\FindNotifiableResources;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class FindNotifiableResourcesController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param FindNotifiableResources $useCase
     * @param FindNotifiableResourcesPresenter $presenter
     * @param Request $request
     *
     * @throws AccessDeniedException|\Throwable
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        FindNotifiableResources $useCase,
        FindNotifiableResourcesPresenter $presenter
    ): Response {

        $this->denyAccessUnlessGrantedForApiConfiguration();

        $requestUid = $request->headers->get('X-Notifications-Resources-UID', null);
        if (! \is_string($requestUid)) {
            $presenter->presentResponse(new InvalidArgumentResponse('Missing header'));
            $this->error(
                'Missing header "X-Notifications-Resources-UID"',
                ['X-Notifications-Resources-UID' => $requestUid]
            );
        } else {
            $useCase($presenter, $requestUid);
        }

        return $presenter->show();
    }
}
