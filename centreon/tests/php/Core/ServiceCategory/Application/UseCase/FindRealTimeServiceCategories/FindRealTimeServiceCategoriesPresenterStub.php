<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories\FindRealTimeServiceCategoriesPresenterInterface;
use Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories\FindRealTimeServiceCategoriesResponse;

class FindRealTimeServiceCategoriesPresenterStub extends AbstractPresenter implements FindRealTimeServiceCategoriesPresenterInterface
{
    public FindRealTimeServiceCategoriesResponse|ResponseStatusInterface $response;

    public function presentResponse(FindRealTimeServiceCategoriesResponse|ResponseStatusInterface $response): void
    {
        $this->response = $response;
    }
}
