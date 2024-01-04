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

declare(strict_types = 1);

namespace Tests\Core\ResourceAccess\Application\UseCase\AddRule;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadRuleRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteRuleRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\AddRule\AddRule;
use Core\ResourceAccess\Application\UseCase\AddRule\AddRuleRequest;
use Core\ResourceAccess\Application\UseCase\AddRule\AddRuleResponse;
use Core\ResourceAccess\Application\UseCase\AddRule\AddRuleValidation;
use Core\ResourceAccess\Domain\Model\DatasetFilter;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\ResourceAccess\Infrastructure\API\AddRule\AddRulePresenterStub;

beforeEach(closure: function (): void {
    $this->presenter = new AddRulePresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new AddRule(
        readRepository: $this->readRepository = $this->createMock(ReadRuleRepositoryInterface::class),
        writeRepository: $this->writeRepository = $this->createMock(WriteRuleRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        dataStorageEngine: $this->createMock(DataStorageEngineInterface::class),
        validator: $this->validator = $this->createMock(AddRuleValidation::class),
        accessGroupRepository: $this->createMock(ReadAccessGroupRepositoryInterface::class)
    );

    $this->request = new AddRuleRequest();
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

    $datasetFilter0 = (new DatasetFilter($datasetFilterData0['type'], $datasetFilterData0['resources']));
    $datasetFilter0->setDatasetFilter(
        new DatasetFilter(
            $datasetFilterData0['dataset_filter']['type'],
            $datasetFilterData0['dataset_filter']['resources']
        )
    );

    $datasetFilter1 = new DatasetFilter($datasetFilterData1['type'], $datasetFilterData1['resources']);

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

it(
    'should present a ForbiddenResponse when a user has insufficient rights',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(RuleException::notAllowed()->getMessage());
    }
);

it(
    'should present a ConflictResponse when name is already used',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('assertIsValidName')
            ->willThrowException(
                RuleException::nameAlreadyExists(
                    Rule::formatName($this->request->name),
                    $this->request->name
                )
            );

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(
                RuleException::nameAlreadyExists(
                    Rule::formatName($this->request->name),
                    $this->request->name
                )->getMessage()
            );
    }
);

it(
    'should present an ErrorResponse when contact ids provided does not exist',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('assertContactIdsAreValid')
            ->willThrowException(
                RuleException::idsDoNotExist(
                    'contactIds',
                    $this->request->contactIds
                )
            );

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
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
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('assertContactGroupIdsAreValid')
            ->willThrowException(
                RuleException::idsDoNotExist(
                    'contactGroupIds',
                    $this->request->contactGroupIds
                )
            );

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
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
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('assertIdsAreValid')
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

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(
                RuleException::idsDoNotExist(
                    $this->request->datasetFilters[0]['type'],
                    $this->request->datasetFilters[0]['resources']
                )->getMessage()
            );
    }
);

it('should present an ErrorResponse if the newly created rule cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->writeRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::errorWhileRetrievingARule()->getMessage());
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validator->expects($this->once())->method('assertIsValidName');
    $this->validator->expects($this->once())->method('assertContactIdsAreValid');
    $this->validator->expects($this->once())->method('assertContactGroupIdsAreValid');
    $this->validator->expects($this->any())->method('assertIdsAreValid');
    $this->writeRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $this->writeRepository
        ->expects($this->once())
        ->method('linkContactsToRule');

    $this->writeRepository
        ->expects($this->once())
        ->method('linkContactGroupsToRule');

    $this->writeRepository
        ->expects($this->any())
        ->method('addDataset');

    $this->writeRepository
        ->expects($this->any())
        ->method('linkDatasetToRule');

    $this->writeRepository
        ->expects($this->any())
        ->method('linkHostsToDataset');

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->rule);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(AddRuleResponse::class)
        ->and($response->id)->toBe($this->rule->getId())
        ->and($response->name)->toBe($this->rule->getName())
        ->and($response->description)->toBe($this->rule->getDescription())
        ->and($response->contactIds)->toBe($this->rule->getLinkedContactIds())
        ->and($response->isEnabled)->toBeTrue()
        ->and($response->contactGroupIds)->toBe($this->rule->getLinkedContactGroupIds())
        ->and($response->datasetFilters[0]['type'])->toBe($this->datasetFilters[0]->getType())
        ->and($response->datasetFilters[0]['resources'])->toBe($this->datasetFilters[0]->getResourceIds())
        ->and($response->datasetFilters[0]['dataset_filter']['type'])->toBe($this->datasetFilters[0]->getDatasetFilter()->getType())
        ->and($response->datasetFilters[0]['dataset_filter']['resources'])->toBe($this->datasetFilters[0]->getDatasetFilter()->getResourceIds())
        ->and($response->datasetFilters[0]['dataset_filter']['dataset_filter'])->toBeNull()
        ->and($response->datasetFilters[1]['type'])->toBe($this->datasetFilters[1]->getType())
        ->and($response->datasetFilters[1]['resources'])->toBe($this->datasetFilters[1]->getResourceIds())
        ->and($response->datasetFilters[1]['dataset_filter'])->toBeNull();
});
