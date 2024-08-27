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

namespace Tests\Core\ResourceAccess\Application\UseCase\DeleteRules;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\MultiStatusResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRules;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRulesRequest;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Tests\Core\ResourceAccess\Infrastructure\API\DeleteRules\DeleteRulesPresenterStub;

beforeEach(closure: function (): void {
    $this->useCase = new DeleteRules(
        readRepository: $this->readRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        writeRepository: $this->writeRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        accessGroupRepository: $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        isCloudPlatform: true
    );

    $this->presenter = new DeleteRulesPresenterStub($this->createMock(PresenterFormatterInterface::class));
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

    $request = new DeleteRulesRequest();
    $request->ids = [1, 2];

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present a Forbidden response when user does not have sufficient rights (not admin)', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'centreon_not_admin', 'not an admin')]
        );

    $request = new DeleteRulesRequest();
    $request->ids = [1, 2];

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present a Multi-Status Response when a bulk delete action is executed', function (): void {
    $request = new DeleteRulesRequest();
    $request->ids = [1, 2];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'customer_admin_acl', 'customer_admin_acl')]
        );

    $this->readRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->willReturnOnConsecutiveCalls(true, false);

    ($this->useCase)($request, $this->presenter);

    $expectedResult = [
        'results' => [
            [
                'self' => 'centreon/api/latest/administration/resource-access/rules/1',
                'status' => 204,
                'message' => null,
            ],
            [
                'self' => 'centreon/api/latest/administration/resource-access/rules/2',
                'status' => 404,
                'message' => 'Resource access rule not found',
            ],
        ],
    ];

    expect($this->presenter->response)
        ->toBeInstanceOf(MultiStatusResponse::class)
        ->and($this->presenter->response->getPayload())
        ->toBeArray()
        ->and($this->presenter->response->getPayload())
        ->toBe($expectedResult);
});

