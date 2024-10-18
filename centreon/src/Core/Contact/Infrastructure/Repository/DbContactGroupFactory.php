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

namespace Core\Contact\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactGroupType;

/**
 * @phpstan-type _ContactGroup array{
 *     cg_id: int,
 *     cg_name: string,
 *     cg_alias: string,
 *     cg_comment?: string,
 *     cg_activate: string,
 *     cg_type: string
 * }
 */
class DbContactGroupFactory
{
    /**
     * @param _ContactGroup $record
     *
     * @throws AssertionFailedException
     *
     * @return ContactGroup
     */
    public static function createFromRecord(array $record): ContactGroup
    {
        return new ContactGroup(
            (int) $record['cg_id'],
            $record['cg_name'],
            $record['cg_alias'],
            $record['cg_comment'] ?? '',
            $record['cg_activate'] === '1',
            $record['cg_type'] === 'local'
                ? ContactGroupType::Local
                : ContactGroupType::Ldap
        );
    }
}
