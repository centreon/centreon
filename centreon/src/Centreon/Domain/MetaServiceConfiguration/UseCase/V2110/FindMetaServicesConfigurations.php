<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\MetaServiceConfiguration\UseCase\V2110;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MetaServiceConfiguration\Interfaces\MetaServiceConfigurationServiceInterface;
use Centreon\Domain\MetaServiceConfiguration\UseCase\V2110\FindMetaServicesConfigurationsResponse;
use Centreon\Domain\MetaServiceConfiguration\Exception\MetaServiceConfigurationException;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This class is designed to represent a use case to find all host categories.
 *
 * @package Centreon\Domain\MetaServiceConfiguration\UseCase\V2110
 */
class FindMetaServicesConfigurations
{
    use LoggerTrait;

    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param MetaServiceConfigurationServiceInterface $metaServiceConfigurationService
     * @param ContactInterface $contact
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly MetaServiceConfigurationServiceInterface $metaServiceConfigurationService,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * Execute the use case for which this class was designed.
     *
     * @return FindMetaServicesConfigurationsResponse
     * @throws AccessDeniedException|MetaServiceConfigurationException
     */
    public function execute(): FindMetaServicesConfigurationsResponse
    {
        if (
            ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_META_SERVICES_READ)
            && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_META_SERVICES_WRITE)
        ) {
            $this->error('Insufficient right for user', ['user_id' => $this->contact->getId()]);

            throw new AccessDeniedException(
                'Insufficient rights (required: ROLE_CONFIGURATION_META_SERVICES_READ or ROLE_CONFIGURATION_META_SERVICES_WRITE)'
            );
        }

        $response = new FindMetaServicesConfigurationsResponse();
        $metaServicesConfigurations = $this->isUserAdmin()
            ? $this->metaServiceConfigurationService->findAllWithoutAcl()
            : $this->metaServiceConfigurationService->findAllWithAcl();
        $response->setMetaServicesConfigurations($metaServicesConfigurations);

        return $response;
    }

   /**
     * Indicates if the current user is admin or not (cloud + onPremise context).
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->contact->isAdmin()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        return ! empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS))
            && $this->isCloudPlatform;
    }
}
