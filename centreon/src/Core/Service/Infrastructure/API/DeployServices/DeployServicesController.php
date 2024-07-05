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

namespace Core\Service\Infrastructure\API\DeployServices;

use Centreon\Application\Controller\AbstractController;
use Core\Service\Application\UseCase\DeployServices\DeployServices;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DeployServicesController extends AbstractController
{
    /**
     * @throws AccessDeniedException
     */
    public function __invoke(DeployServices $useCase, DefaultPresenter $presenter, int $hostId): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($presenter, $hostId);

        return $presenter->show();
    }
}
