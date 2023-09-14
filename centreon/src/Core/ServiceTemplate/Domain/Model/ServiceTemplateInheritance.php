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

namespace Core\ServiceTemplate\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class ServiceTemplateInheritance
{
    /**
     * @param int $parentId
     * @param int $childId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $parentId,
        private readonly int $childId,
    ) {
        Assertion::positiveInt($parentId, 'ParentServiceTemplate::parentID');
        Assertion::positiveInt($childId, 'ParentServiceTemplate::childId');
    }

    /**
     * @return int
     */
    public function getParentId(): int
    {
        return $this->parentId;
    }

    /**
     * @return int
     */
    public function getChildId(): int
    {
        return $this->childId;
    }

    /**
     * Return an ordered line of inheritance for a service template.
     * (This is a recursive method).
     *
     * @param int $serviceId
     * @param ServiceTemplateInheritance[] $parents
     *
     * @return int[]
     */
    public static function createInheritanceLine(int $serviceId, array $parents): array
    {
        foreach ($parents as $index => $parent) {
            if ($parent->getChildId() === $serviceId) {
                unset($parents[$index]);

                return array_merge(
                    [$parent->getParentId()],
                    self::createInheritanceLine($parent->getParentId(), $parents)
                );
            }
        }

        return [];
    }
}
