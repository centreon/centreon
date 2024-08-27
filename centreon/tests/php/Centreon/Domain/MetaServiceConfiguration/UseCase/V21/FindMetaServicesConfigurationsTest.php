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

namespace Tests\Centreon\Domain\MetaServiceConfiguration\UseCase\V21;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\MetaServiceConfiguration\MetaServiceConfigurationService;
use Centreon\Domain\MetaServiceConfiguration\Model\MetaServiceConfiguration;
use Centreon\Domain\MetaServiceConfiguration\UseCase\V2110\FindMetaServicesConfigurations;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Centreon\Domain\MetaServiceConfiguration\Model\MetaServiceConfigurationTest;

/**
 * @package Tests\Centreon\Domain\MetaServiceConfiguration\UseCase\V21
 */
class FindMetaServicesConfigurationsTest extends TestCase
{
    private MetaServiceConfigurationService&\PHPUnit\Framework\MockObject\MockObject $metaServiceConfigurationService;

    private MetaServiceConfiguration $metaServiceConfiguration;

    private ReadAccessGroupRepositoryInterface $accessGroupRepository;

    private ContactInterface&\PHPUnit\Framework\MockObject\MockObject $contact;

    protected function setUp(): void
    {
        $this->metaServiceConfigurationService = $this->createMock(MetaServiceConfigurationService::class);
        $this->metaServiceConfiguration = MetaServiceConfigurationTest::createEntity();
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $this->contact = $this->createMock(ContactInterface::class);
    }

    /**
     * Test as admin user
     */
    public function testExecuteAsAdmin(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);

        $this->metaServiceConfigurationService
            ->expects($this->once())
            ->method('findAllWithoutAcl')
            ->willReturn([$this->metaServiceConfiguration]);

        $findMetaServiceConfigurations = new FindMetaServicesConfigurations(
            $this->metaServiceConfigurationService,
            $this->contact,
            $this->accessGroupRepository,
            false
        );
        $response = $findMetaServiceConfigurations->execute();
        $this->assertCount(1, $response->getMetaServicesConfigurations());
    }

    /**
     * Test as non admin user
     */
    public function testExecuteAsNonAdmin(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(false);

        $this->metaServiceConfigurationService
            ->expects($this->once())
            ->method('findAllWithAcl')
            ->willReturn([$this->metaServiceConfiguration]);

        $findMetaServiceConfigurations = new FindMetaServicesConfigurations(
            $this->metaServiceConfigurationService,
            $this->contact,
            $this->accessGroupRepository,
            false
        );
        $response = $findMetaServiceConfigurations->execute();
        $this->assertCount(1, $response->getMetaServicesConfigurations());
    }
}
