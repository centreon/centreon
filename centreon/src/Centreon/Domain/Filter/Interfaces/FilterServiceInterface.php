<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Filter\Interfaces;

use Centreon\Domain\Filter\Filter;
use Centreon\Domain\Filter\FilterCriteria;
use Centreon\Domain\Filter\FilterException;

interface FilterServiceInterface
{
    /**
     * Used to filter requests according to a contact.
     * If the filter is defined, all requests will use the ACL of the contact
     * to fetch data.
     *
     * @param mixed $contact Contact to use as a ACL filter
     * @throws \Exception
     * @return FilterServiceInterface
     */
    public function filterByContact($contact): FilterServiceInterface;

    /**
     * Add filter.
     *
     * @param Filter $filter
     * @throws FilterException
     * @return int created filter id
     */
    public function addFilter(Filter $filter): int;

    /**
     * Update filter.
     *
     * @param Filter $filter
     * @throws FilterException
     * @return void
     */
    public function updateFilter(Filter $filter): void;

    /**
     * Check filter criterias
     * Remove object if does not exist anymore
     * Rename object if has been renamed since filter creation
     *
     * @param FilterCriteria[] $criterias
     * @return void
     */
    public function checkCriterias(array $criterias): void;

    /**
     * Delete filter.
     *
     * @param Filter $filter
     * @throws FilterException
     * @return void
     */
    public function deleteFilter(Filter $filter): void;

    /**
     * Find filters.
     *
     * @param int $userId current user id
     * @param string $pageName page name
     * @throws FilterException
     * @return Filter[]
     */
    public function findFiltersByUserId(int $userId, string $pageName): array;

    /**
     * Find filter by user id and filter id.
     *
     * @param int $userId current user id
     * @param string $pageName page name
     * @param int $filterId Filter id to search
     * @throws FilterException
     * @return Filter
     */
    public function findFilterByUserId(int $userId, string $pageName, int $filterId): ?Filter;
}
