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

namespace Core\Migration\Infrastructure\API\ExecuteMigrations;

use Centreon\Application\Controller\AbstractController;
use Core\Migration\Application\UseCase\ExecuteMigrations\ExecuteMigrations;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Core\Migration\Application\UseCase\ExecuteMigrations\ExecuteMigrationsRequest;

final class ExecuteMigrationsController extends AbstractController
{
    /**
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(Request $request, ExecuteMigrations $useCase, ExecuteMigrationsPresenter $presenter): Response
    {
        $this->denyAccessUnlessAdmin();

        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/ExecuteMigrationsSchema.json');

        $migrationRequest = $this->createRequestDto($data);
        $useCase($migrationRequest, $presenter);

        return $presenter->show();
    }

    private function createRequestDto(array $data): ExecuteMigrationsRequest
    {
        $migrationRequest = new ExecuteMigrationsRequest();
        $migrationRequest->names = $data['names'];

        return $migrationRequest;
    }
}
