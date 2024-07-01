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

namespace Tests\Core\ResourceAccess\Application\UseCase\FindRule;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Configuration\MetaService\Repository\ReadMetaServiceRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Providers\HostCategoryProvider;
use Core\ResourceAccess\Application\Providers\HostGroupProvider;
use Core\ResourceAccess\Application\Providers\HostProvider;
use Core\ResourceAccess\Application\Providers\MetaServiceProvider;
use Core\ResourceAccess\Application\Providers\ServiceCategoryProvider;
use Core\ResourceAccess\Application\Providers\ServiceGroupProvider;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRule;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRuleResponse;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\MetaServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Tests\Core\ResourceAccess\Infrastructure\API\FindRule\FindRulePresenterStub;

beforeEach(closure: function (): void {
    foreach ([
        HostFilterType::class,
        HostGroupFilterType::class,
        HostCategoryFilterType::class,
        ServiceFilterType::class,
        ServiceGroupFilterType::class,
        ServiceCategoryFilterType::class,
        MetaServiceFilterType::class,
    ] as $className) {
        $filterTypes[] = new $className();
    }

    $datasetValidator = new DatasetFilterValidator(new \ArrayObject($filterTypes));

    $providers = new \ArrayObject([
        new HostProvider($this->createMock(ReadHostRepositoryInterface::class)),
        new HostGroupProvider($this->createMock(ReadHostGroupRepositoryInterface::class)),
        new HostCategoryProvider($this->createMock(ReadHostCategoryRepositoryInterface::class)),
        new ServiceGroupProvider($this->createMock(ReadServiceGroupRepositoryInterface::class)),
        new ServiceCategoryProvider($this->createMock(ReadServiceCategoryRepositoryInterface::class)),
        new MetaServiceProvider($this->createMock(ReadMetaServiceRepositoryInterface::class)),
    ]);

    $hostDatasetFilter = new DatasetFilter('host', [1], $datasetValidator);
    $datasetFilter = new DatasetFilter('hostgroup', [1], $datasetValidator);
    $datasetFilter->setDatasetFilter($hostDatasetFilter);

    $this->rule = new Rule(
        id: 1,
        name: 'ResourceAccess',
        description: 'ResourceAccessDescription',
        linkedContacts: [1],
        linkedContactGroups: [2],
        datasets: [$datasetFilter]
    );
    $this->user = $this->createMock(ContactInterface::class);
    $this->repository = $this->createMock(ReadResourceAccessRepositoryInterface::class);
    $this->presenter = new FindRulePresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->contactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);

    $this->useCase = new FindRule(
        user: $this->user,
        accessGroupRepository: $this->accessGroupRepository,
        repository: $this->repository,
        contactRepository: $this->contactRepository,
        contactGroupRepository: $this->contactGroupRepository,
        datasetFilterValidator: $datasetValidator,
        repositoryProviders: $providers,
        isCloudPlatform: true
    );
});

it('should present a Forbidden response when user does not have sufficient rights (missing page access)', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'customer_admin_acl', 'not an admin')]
        );

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present a Forbidden response when user does not have sufficient rights (not an admin)', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'lame_acl', 'not an admin')]
        );

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception occurs', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'customer_admin_acl', 'not an admin')]
        );

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $exception = new \Exception();
    $this->repository
        ->expects($this->once())
        ->method('findById')
        ->with(1)
        ->willThrowException($exception);

    ($this->useCase)(1, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::errorWhileSearchingRules()->getMessage());
});

it('should present a FindRuleResponse when no error occurs', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'customer_admin_acl', 'not an admin')]
        );

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findById')
        ->with(1)
        ->willReturn($this->rule);

    $this->contactRepository
        ->expects($this->any())
        ->method('findNamesByIds')
        ->with(...$this->rule->getLinkedContactIds())
        ->willReturn(['1' => ['id' => 1, 'name' => 'contact1']]);

    $this->contactGroupRepository
        ->expects($this->any())
        ->method('findNamesByIds')
        ->with(...$this->rule->getLinkedContactGroupIds())
        ->willReturn(['1' => ['id' => 1, 'name' => 'contactgroup1']]);

    ($this->useCase)(1, $this->presenter);
    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(FindRuleResponse::class)
        ->and($response->id)->toBe($this->rule->getId())
        ->and($response->name)->toBe($this->rule->getName())
        ->and($response->description)->toBe($this->rule->getDescription())
        ->and($response->isEnabled)->toBe($this->rule->isEnabled());
});
