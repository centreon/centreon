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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\MediaCriteria;
use App\Shared\Domain\Collection;

interface MediaRepository
{
    /**
     * @return \IteratorAggregate<int, Media>&\Countable
     */
    public function findAll(?MediaCriteria $criteria = null): \IteratorAggregate&\Countable;

    /**
     * Every requested id's media, for bulk display purposes (e.g. a sibling aggregate that only
     * references a media by id, such as a host's icon). An id absent from the result no longer exists.
     *
     * @param Collection<MediaId> $ids
     *
     * @return Collection<Media> indexed by media id
     */
    public function findByIds(Collection $ids): Collection;
}
