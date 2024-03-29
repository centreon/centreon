<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\HostConfiguration\Interfaces\HostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\HostConfiguration\Model\HostCategory;
use Centreon\Domain\HostConfiguration\Host;

/**
 * This interface gathers all the reading operations on the host category repository.
 *
 * @package Centreon\Domain\HostConfiguration\Interfaces
 */
interface HostCategoryReadRepositoryInterface
{
    /**
     * Find a host category by id.
     *
     * @param int $categoryId Id of the host category to be found
     * @return HostCategory|null
     * @throws \Throwable
     */
    public function findById(int $categoryId): ?HostCategory;

    /**
     * Find a host category by id and contact.
     *
     * @param int $categoryId Id of the host category to be found
     * @param ContactInterface $contact Contact related to host category
     * @return HostCategory|null
     * @throws \Throwable
     */
    public function findByIdAndContact(int $categoryId, ContactInterface $contact): ?HostCategory;

    /**
     * Find host categories by name (for admin user).
     *
     * @param string[] $categoriesName List of names of host categories to be found
     * @return HostCategory[]
     * @throws \Throwable
     */
    public function findByNames(array $categoriesName): array;

    /**
     * Find host categories by host.
     *
     * @param Host $host
     * @return HostCategory[]
     */
    public function findAllByHost(Host $host): array;
}
