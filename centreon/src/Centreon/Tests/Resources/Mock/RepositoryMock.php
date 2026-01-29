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

namespace Centreon\Tests\Resources\Mock;

use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

/**
 * Mock of repository class
 */
class RepositoryMock extends ServiceEntityRepository
{
    /**
     * {@inheritDoc}
     */
    public static function entityClass(): string
    {
        return EntityMock::class;
    }

    /**
     * Find entity by parameters
     *
     * @param array $params
     * @return EntityMock|null
     */
    public function findOneBy(array $params)
    {
        return null;
    }

    /**
     * Validate entity
     *
     * @param EntityMock $object
     * @return bool
     */
    public function validateEntity(EntityMock $object): bool
    {
        return false;
    }
}
