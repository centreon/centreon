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

namespace Core\Contact\Application\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Domain\Model\ContactGroup;

interface WriteContactGroupRepositoryInterface
{
    /**
     * Delete all contact groups for a given user.
     *
     * @param ContactInterface $user
     */
    public function deleteContactGroupsForUser(ContactInterface $user): void;

    /**
     * Insert a contact group for a given user.
     *
     * @param ContactInterface $user
     * @param ContactGroup $contactGroup
     */
    public function insertContactGroupForUser(ContactInterface $user, ContactGroup $contactGroup): void;
}
