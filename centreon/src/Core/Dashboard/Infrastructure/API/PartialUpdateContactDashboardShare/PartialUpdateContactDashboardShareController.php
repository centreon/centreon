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

namespace Core\Dashboard\Infrastructure\API\PartialUpdateContactDashboardShare;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\UseCase\PartialUpdateContactDashboardShare\PartialUpdateContactDashboardShare;
use Core\Dashboard\Application\UseCase\PartialUpdateContactDashboardShare\PartialUpdateContactDashboardSharePresenterInterface;
use Core\Dashboard\Application\UseCase\PartialUpdateContactDashboardShare\PartialUpdateContactDashboardShareRequest;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartialUpdateContactDashboardShareController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PartialUpdateContactDashboardShare $useCase
     * @param PartialUpdateContactDashboardSharePresenter $presenter
     * @param int $dashboardId
     * @param int $contactId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $dashboardId,
        int $contactId,
        Request $request,
        PartialUpdateContactDashboardShare $useCase,
        PartialUpdateContactDashboardSharePresenterInterface $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     role?: string
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateContactDashboardShareSchema.json');

            $dto = new PartialUpdateContactDashboardShareRequest();

            if (\array_key_exists('role', $dataSent)) {
                $dto->role = DashboardSharingRoleConverter::fromString($dataSent['role']);
            }

            $useCase($dashboardId, $contactId, $dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }
}
