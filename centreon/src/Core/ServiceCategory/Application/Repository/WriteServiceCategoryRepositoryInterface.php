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

namespace Core\ServiceCategory\Application\Repository;

use Core\ServiceCategory\Domain\Model\NewServiceCategory;

interface WriteServiceCategoryRepositoryInterface
{
    /**
     * Delete service category by id.
     *
     * @param int $serviceCategoryId
     *
     * @throws \Throwable
     */
    public function deleteById(int $serviceCategoryId): void;

    /**
     * Add a service category and return its id.
     *
     * @param NewServiceCategory $serviceCategory
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewServiceCategory $serviceCategory): int;

    /**
     * Link a service to a list of service categories.
     *
     * @param int $serviceId
     * @param list<int> $serviceCategoriesIds
     *
     * @throws \Throwable
     */
    public function linkToService(int $serviceId, array $serviceCategoriesIds): void;

    /**
     * Unlink a service from a list of service categories.
     *
     * @param int $serviceId
     * @param list<int> $serviceCategoriesIds
     *
     * @throws \Throwable
     */
    public function unlinkFromService(int $serviceId, array $serviceCategoriesIds): void;
}
