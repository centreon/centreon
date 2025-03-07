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

namespace Core\Dashboard\Infrastructure\API\FindDashboardContacts;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\FindDashboardContacts;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[IsGranted(
    'dashboard_access_editor',
    null,
    'You do not have sufficient rights to list the dashboard contacts'
)]
final class FindDashboardContactsController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param FindDashboardContacts $useCase
     * @param StandardPresenter $presenter
     * @throws ExceptionInterface
     * @return Response
     */
    public function __invoke(
        FindDashboardContacts $useCase,
        StandardPresenter $presenter
    ): Response {
        $response = $useCase();

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString(
            $presenter->present($response, ['groups' => ['FindDashboardContacts:Read']])
        );
    }
}
