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

declare(strict_types = 1);

namespace Tests\Core\Host\Infrastructure\API\FindHosts;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsPresenterInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsResponse;

class FindHostsPresenterStub extends AbstractPresenter implements FindHostsPresenterInterface
{
    public FindHostsResponse|ResponseStatusInterface $response;

    public function presentResponse(FindHostsResponse|ResponseStatusInterface $response): void
    {
        $this->response = $response;
    }
}
