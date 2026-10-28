<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceListingRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;

/**
 * Returns the seeded items as they are: filtering, sorting and scoping are SQL, pinned by the integration
 * tests of the real repository. It records the criteria it is asked with, so a caller's mapping can be checked.
 */
final class FakeServiceListingRepository implements ServiceListingRepository
{
    /** @var list<ServiceListItem> */
    public array $items = [];

    /** @var list<ServiceListingCriteria> */
    public array $requestedCriteria = [];

    public function findAll(ServiceListingCriteria $criteria): \IteratorAggregate&\Countable
    {
        $this->requestedCriteria[] = $criteria;

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            return new Collection($this->items, ServiceListItem::class);
        }

        return new InMemoryPaginator(
            new Collection(array_slice($this->items, $pagination->getOffset(), $pagination->itemsPerPage), ServiceListItem::class),
            count($this->items),
            $pagination->page,
            $pagination->itemsPerPage,
        );
    }
}
