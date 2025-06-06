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

namespace Tests\Core\Resources\Application\UseCase\ExportResources;

use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesPresenterInterface;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesResponse;

/**
 * Class
 *
 * @class ExportResourcesPresenterStub
 * @package Tests\Core\Resources\Application\UseCase\ExportResources
 */
class ExportResourcesPresenterStub implements ExportResourcesPresenterInterface
{
    /** @var ExportResourcesResponse|ResponseStatusInterface */
    public ExportResourcesResponse|ResponseStatusInterface $response;

    /**
     * @param ResponseStatusInterface|ExportResourcesResponse $response
     *
     * @return void
     */
    public function presentResponse(ResponseStatusInterface|ExportResourcesResponse $response): void
    {
        $this->response = $response;
    }
}
