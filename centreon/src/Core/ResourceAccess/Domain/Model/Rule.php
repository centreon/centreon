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

class Rule extends NewRule
{
    private string $shortName = '';

    /**
     * @param int $id
     * @param string $name
     * @param ?string $description
     * @param int[] $linkedContacts
     * @param int[] $linkedContactGroups
     * @param DatasetFilter[] $datasets
     * @param bool $isEnabled
     * @param bool $applyToAllContacts
     * @param bool $applyToAllContactGroups
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        ?string $description = null,
        bool $applyToAllContacts = false,
        array $linkedContacts = [],
        bool $applyToAllContactGroups = false,
        array $linkedContactGroups = [],
        array $datasets = [],
        bool $isEnabled = true
    ) {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($id, "{$this->shortName}::id");

        parent::__construct(
            name: $name,
            description: $description,
            applyToAllContacts: $applyToAllContacts,
            linkedContactIds: $linkedContacts,
            applyToAllContactGroups: $applyToAllContactGroups,
            linkedContactGroupIds: $linkedContactGroups,
            datasetFilters: $datasets,
            isEnabled: $isEnabled
        );
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param null|string $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $this->name = $name;
        Assertion::notEmptyString($this->name, "{$this->shortName}::name");
        Assertion::minLength($this->name, 1, "{$this->shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$this->shortName}::name");
    }

    /**
     * @param bool $isEnabled
     */
    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @param int[] $contacts
     */
    public function setLinkedContacts(array $contacts): void
    {
        $this->linkedContactIds = $contacts;
    }

    /**
     * @param int[] $contactGroups
     */
    public function setLinkedContactGroups(array $contactGroups): void
    {
        $this->linkedContactGroupIds = $contactGroups;
    }

    /**
     * @param bool $applyToAllContacts
     */
    public function setApplyToAllContacts(bool $applyToAllContacts): void
    {
        $this->applyToAllContacts = $applyToAllContacts;
    }

    /**
     * @param bool $applyToAllContactGroups
     */
    public function setApplyToAllContactGroups(bool $applyToAllContactGroups): void
    {
        $this->applyToAllContactGroups = $applyToAllContactGroups;
    }

    /**
     * @param DatasetFilter $datasetFilter
     */
    public function addDataset(DatasetFilter $datasetFilter): void
    {
        $this->datasetFilters[] = $datasetFilter;
    }

    /**
     * @param DatasetFilter[] $datasets
     */
    public function setDatasets(array $datasets): void
    {
        foreach ($datasets as $dataset) {
            $this->addDataset($dataset);
        }
    }
}

