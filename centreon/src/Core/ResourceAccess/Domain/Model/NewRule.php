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

namespace Core\ResourceAccess\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;

class NewRule
{
    public const MAX_NAME_LENGTH = 255;

    /**
     * @param string $name
     * @param null|string $description
     * @param bool $applyToAllContacts
     * @param int[] $linkedContactIds
     * @param bool $applyToAllContactGroups
     * @param int[] $linkedContactGroupIds
     * @param DatasetFilter[] $datasetFilters
     * @param bool $isEnabled
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected ?string $description = null,
        protected bool $applyToAllContacts = false,
        protected array $linkedContactIds = [],
        protected bool $applyToAllContactGroups = false,
        protected array $linkedContactGroupIds = [],
        protected array $datasetFilters = [],
        protected bool $isEnabled = true,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = self::formatName($name);

        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");

        if ([] !== $linkedContactIds) {
            Assertion::arrayOfTypeOrNull('int', $this->linkedContactIds, "{$shortName}::linkedContactIds");
        }

        if ([] !== $linkedContactGroupIds) {
            Assertion::arrayOfTypeOrNull('int', $this->linkedContactGroupIds, "{$shortName}::linkedContactGroupIds");
        }

        Assertion::notEmpty($this->datasetFilters, "{$shortName}::datasetFilters");

        $this->assertContactAndOrContactGroup();
    }

    /**
     * Format a string as per domain rules for a rule name.
     *
     * @param string $name
     */
    final public static function formatName(string $name): string
    {
        return str_replace(' ', '_', trim($name));
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @return int[]
     */
    public function getLinkedContactIds(): array
    {
        return $this->linkedContactIds;
    }

    /**
     * @return int[]
     */
    public function getLinkedContactGroupIds(): array
    {
        return $this->linkedContactGroupIds;
    }

    /**
     * @return DatasetFilter[]
     */
    public function getDatasetFilters(): array
    {
        return $this->datasetFilters;
    }

    /**
     * @return bool
     */
    public function doesApplyToAllContacts(): bool
    {
        return $this->applyToAllContacts;
    }

    /**
     * @return bool
     */
    public function doesApplyToAllContactGroups(): bool
    {
        return $this->applyToAllContactGroups;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertContactAndOrContactGroup(): void
    {
        if (
            $this->linkedContactIds === []
            && $this->linkedContactGroupIds === []
            && $this->applyToAllContacts === false
            && $this->applyToAllContactGroups === false
        ) {
            throw new \InvalidArgumentException('At least one contact or contactgroup should be linked to the rule');
        }
    }
}

