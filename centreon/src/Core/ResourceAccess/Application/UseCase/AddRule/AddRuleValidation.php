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

namespace Core\ResourceAccess\Application\UseCase\AddRule;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Configuration\MetaService\Repository\ReadMetaServiceRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadRuleRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilterTypeConverter;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;

class AddRuleValidation
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadRuleRepositoryInterface $repository,
        private readonly ReadContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly ReadHostRepositoryInterface $hostRepository,
        private readonly ReadHostGroupRepositoryInterface $hostgroupRepository,
        private readonly ReadHostCategoryRepositoryInterface $hostCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $servicegroupRepository,
        private readonly ReadServiceCategoryRepositoryInterface $serviceCategoryRepository,
        private readonly ReadServiceRepositoryInterface $serviceRepository,
        private readonly ReadMetaServiceRepositoryInterface $metaRepository
    ) {
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
        $resourceType = DatasetFilterTypeConverter::fromString($type);

        $validIds = match ($resourceType) {
            DatasetFilterType::Host => $this->hostRepository->exist($ids),
            DatasetFilterType::Hostgroup => $this->hostgroupRepository->exist($ids),
            DatasetFilterType::HostCategory => $this->hostCategoryRepository->exist($ids),
            DatasetFilterType::Servicegroup => $this->servicegroupRepository->exist($ids),
            DatasetFilterType::Service => $this->serviceRepository->exist($ids),
            DatasetFilterType::ServiceCategory => $this->serviceCategoryRepository->exist($ids),
            DatasetFilterType::MetaService => $this->metaRepository->exist($ids)
        };

        if ([] !== ($invalidIds = array_diff($ids, $validIds))) {
            throw RuleException::idsDoNotExist($type, $invalidIds);
        }
    }
}

