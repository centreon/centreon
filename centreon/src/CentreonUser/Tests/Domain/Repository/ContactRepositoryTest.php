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

namespace CentreonUser\Tests\Domain\Repository;

use Centreon\Tests\Resources\Traits;
use CentreonUser\Domain\Entity\Contact;
use CentreonUser\Domain\Repository\ContactRepository;
use PHPUnit\Framework\TestCase;

/**
 * @group CentreonUser
 * @group ORM-repository
 */
class ContactRepositoryTest extends TestCase
{
    use Traits\CheckListOfIdsTrait;

    /**
     * Test the method checkListOfIds.
     */
    public function testCheckListOfIds(): void
    {
        $this->checkListOfIdsTrait(
            ContactRepository::class,
            'checkListOfIds',
            Contact::TABLE,
            Contact::ENTITY_IDENTIFICATOR_COLUMN
        );
    }
}
