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

namespace Core\ResourceAccess\Application\UseCase\UpdateRule;

use Centreon\Domain\Log\LoggerTrait;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Providers\DatasetProviderInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\NewRule;

class UpdateRuleValidation
{
    use LoggerTrait;

    /** @var DatasetProviderInterface[] */
    private array $repositoryProviders;

    /**
     * @param ReadResourceAccessRepositoryInterface $repository
     * @param ReadContactRepositoryInterface $contactRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param \Traversable<DatasetProviderInterface> $repositoryProviders
     */
    public function __construct(
        private readonly ReadResourceAccessRepositoryInterface $repository,
        private readonly ReadContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        \Traversable $repositoryProviders
    ) {
        $this->repositoryProviders = iterator_to_array($repositoryProviders);
    }

    /**
     * Validates that the name provided for the rule is not already used.
     *
     * @param string $name
     *
     * @throws RuleException
     */
    public function assertIsValidName(string $name): void
    {
        $this->debug('Check that resource access rule name is not already used', ['name' => $name]);
        if ($this->repository->existsByName(NewRule::formatName($name))) {
            $this->error('Resource access rule name already used', ['name' => $name]);

            throw RuleException::nameAlreadyExists(NewRule::formatName($name), $name);
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws RuleException
     */
    public function assertContactIdsAreValid(array $contactIds): void
    {
        $contactIds = array_values(array_unique($contactIds));
        $validIds = $this->contactRepository->exist($contactIds);

        if ([] !== ($invalidIds = array_diff($contactIds, $validIds))) {
            throw RuleException::idsDoNotExist('contactIds', $invalidIds);
        }
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @throws RuleException
     */
    public function assertContactGroupIdsAreValid(array $contactGroupIds): void
    {
        $contactGroupIds = array_values(array_unique($contactGroupIds));
        $validIds = $this->contactGroupRepository->exist($contactGroupIds);

        if ([] !== ($invalidIds = array_diff($contactGroupIds, $validIds))) {
            throw RuleException::idsDoNotExist('contactGroupIds', $invalidIds);
        }
    }

    /**
     * @param string $type
     * @param int[] $ids
     *
     * @throws RuleException
     */
    public function assertIdsAreValid(string $type, array $ids): void
    {
        $validIds = [];
        foreach ($this->repositoryProviders as $repository) {
            if ($repository->isValidFor($type) === true) {
                $validIds = $repository->areResourcesValid($ids);
            }
        }

        if ([] !== ($invalidIds = array_diff($ids, $validIds))) {
            throw RuleException::idsDoNotExist($type, $invalidIds);
        }
    }

    /**
     * @param int[] $contactIds
     * @param int[] $contactGroupIds
     * @param bool $applyToAllContacts
     * @param bool $applyToAllContactGroups
     *
     * @throws RuleException
     */
    public function assertContactsAndContactGroupsAreNotEmpty(
        array $contactIds,
        array $contactGroupIds,
        bool $applyToAllContacts,
        bool $applyToAllContactGroups
    ): void {
        if (
            [] === $contactIds
            && [] === $contactGroupIds
            && $applyToAllContacts === false
            && $applyToAllContactGroups === false
        ) {
            throw RuleException::noLinkToContactsOrContactGroups();
        }
    }
}

