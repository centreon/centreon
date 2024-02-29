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

namespace Tests\Core\ResourceAccess\Application\UseCase\UpdateRule;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRule;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRuleRequest;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRuleValidation;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\MetaServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Tests\Core\ResourceAccess\Infrastructure\API\UpdateRule\UpdateRulePresenterStub;

beforeEach(closure: function (): void {
    $this->presenter = new UpdateRulePresenterStub($this->createMock(PresenterFormatterInterface::class));

    foreach ([
        HostFilterType::class,
        HostGroupFilterType::class,
        HostCategoryFilterType::class,
        ServiceFilterType::class,
        ServiceGroupFilterType::class,
        ServiceCategoryFilterType::class,
        MetaServiceFilterType::class,
    ] as $className) {
        $this->filterTypes[] = new $className();
    }

    $this->datasetValidator = new DatasetFilterValidator(new \ArrayObject($this->filterTypes));

    $this->contactRepository = $this->createMock(ReadContactRepositoryInterface::class);

    $this->useCase = new UpdateRule(
        user: $this->user = $this->createMock(ContactInterface::class),
        accessGroupRepository: $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readRepository: $this->readRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        writeRepository: $this->writeRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        validator: $this->validator = $this->createMock(UpdateRuleValidation::class),
        datasetValidator: $this->datasetValidator,
        dataStorageEngine: $this->createMock(DataStorageEngineInterface::class),
    );

    $this->request = new UpdateRuleRequest();
    $this->request->name = 'Rule 1';
    $this->request->description = 'Description of Rule 1';
    $this->request->isEnabled = true;
    $this->request->contactIds = [1, 2];
    $this->request->contactGroupIds = [3, 4];

    $datasetFilterData0 = [
        'type' => 'hostgroup',
        'resources' => [11, 12],
        'dataset_filter' => [
            'type' => 'host',
            'resources' => [110, 120],
            'dataset_filter' => null,
        ],
    ];

    $datasetFilterData1 = [
        'type' => 'host',
        'resources' => [111, 121],
        'dataset_filter' => null,
    ];

    $this->request->datasetFilters = [$datasetFilterData0, $datasetFilterData1];

    $datasetFilter0 = new DatasetFilter(
        $datasetFilterData0['type'],
        $datasetFilterData0['resources'],
        $this->datasetValidator
    );

    $datasetFilter0->setDatasetFilter(
        new DatasetFilter(
            $datasetFilterData0['dataset_filter']['type'],
            $datasetFilterData0['dataset_filter']['resources'],
            $this->datasetValidator
        )
    );

    $datasetFilter1 = new DatasetFilter(
        $datasetFilterData1['type'],
        $datasetFilterData1['resources'],
        $this->datasetValidator
    );

    $this->datasetFilters = [$datasetFilter0, $datasetFilter1];

    $this->rule = new Rule(
        id: 1,
        name: Rule::formatName($this->request->name),
        description: $this->request->name,
        linkedContacts: $this->request->contactIds,
        linkedContactGroups: $this->request->contactGroupIds,
        datasets: $this->datasetFilters,
        isEnabled: true
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

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present a Forbidden response when user does not have sufficient rights (not an admin)', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'lame_acl', 'not an admin')]
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it(
    'should present a NotFoundResponse when ruleId requested does not exist',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present ConflictResponse when name provided is already used',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        $this->request->name = 'fake-existing-name';

        $this->validator
            ->expects($this->once())
            ->method('assertIsValidName')
            ->willThrowException(
                RuleException::nameAlreadyExists(
                    NewRule::formatName($this->request->name),
                    $this->request->name
                )
            );

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->responseStatus->getMessage())
            ->toBe(
                RuleException::nameAlreadyExists(
                    NewRule::formatName($this->request->name),
                    $this->request->name
                )->getMessage()
            );
    }
);

it(
    'should present a RuleException when contacts and contactgroups are empty',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        $this->validator
            ->expects($this->once())
            ->method('assertContactsAndContactGroupsAreNotEmpty')
            ->willThrowException(
                RuleException::noLinkToContactsOrContactGroups()
            );

        $this->request->contactIds = [];
        $this->request->contactGroupIds = [];

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->responseStatus->getMessage())
            ->toBe(
                RuleException::noLinkToContactsOrContactGroups()->getMessage()
            );
    }
);

it(
    'should present an ErrorResponse when contact ids provided does not exist',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        $this->validator
            ->expects($this->once())
            ->method('assertContactIdsAreValid')
            ->willThrowException(
                RuleException::idsDoNotExist('contactIds', [10])
            );

        $this->request->contactIds = [10];

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->responseStatus->getMessage())
            ->toBe(
                RuleException::idsDoNotExist(
                    'contactIds',
                    $this->request->contactIds
                )->getMessage()
            );
    }
);

it(
    'should present an ErrorResponse when contact group ids provided does not exist',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        $this->validator
            ->expects($this->once())
            ->method('assertContactGroupIdsAreValid')
            ->willThrowException(
                RuleException::idsDoNotExist('contactGroupIds', [10])
            );

        $this->request->contactGroupIds = [10];

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->responseStatus->getMessage())
            ->toBe(
                RuleException::idsDoNotExist(
                    'contactGroupIds',
                    $this->request->contactGroupIds
                )->getMessage()
            );
    }
);

it(
    'should present an ErrorResponse when resources provided does not exist',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        $this->validator
            ->expects($this->once())
            ->method('assertResourceIdsAreValid')
            ->with(
                $this->request->datasetFilters[0]['type'],
                $this->request->datasetFilters[0]['resources']
            )
            ->willThrowException(
                RuleException::idsDoNotExist(
                    $this->request->datasetFilters[0]['type'],
                    $this->request->datasetFilters[0]['resources']
                )
            );

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->responseStatus->getMessage())
            ->toBe(
                RuleException::idsDoNotExist(
                    $this->request->datasetFilters[0]['type'],
                    $this->request->datasetFilters[0]['resources']
                )->getMessage()
            );
    }
);

it(
    'should present a NoContentResponse when everything goes well',
    function (): void {
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

        $this->readRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->rule);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);
