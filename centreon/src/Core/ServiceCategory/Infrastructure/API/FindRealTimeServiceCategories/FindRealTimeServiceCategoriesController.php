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

namespace Core\ServiceCategory\Infrastructure\API\FindRealTimeServiceCategories;

use Centreon\Application\Controller\AbstractController;
use Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories\FindRealTimeServiceCategories;
use Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories\FindRealTimeServiceCategoriesPresenterInterface;

final class FindRealTimeServiceCategoriesController extends AbstractController
{
    /**
     * @param FindRealTimeServiceCategories $useCase
     * @param FindRealTimeServiceCategoriesPresenterInterface $presenter
     *
     * @return object
     */
    public function __invoke(
        FindRealTimeServiceCategories $useCase,
        FindRealTimeServiceCategoriesPresenterInterface $presenter
    ): object {
        /**
         * Access denied if no rights given to the realtime for the current user.
         */
        $this->denyAccessUnlessGrantedForApiRealtime();

        $useCase($presenter);

        return $presenter->show();
    }
}
