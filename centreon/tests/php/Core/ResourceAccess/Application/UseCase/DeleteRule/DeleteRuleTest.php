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

namespace Tests\Core\ResourceAccess\Application\UseCase\DeleteRule;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\DeleteRule\DeleteRule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Tests\Core\ResourceAccess\Infrastructure\API\DeleteRule\DeleteRulePresenterStub;

beforeEach(closure: function (): void {
    $this->useCase = new DeleteRule(
        readRepository: $this->readRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        writeRepository: $this->writeRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        accessGroupRepository: $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        isCloudPlatform: true
    );

    $this->presenter = new DeleteRulePresenterStub($this->createMock(PresenterFormatterInterface::class));
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

it('should present a Forbidden response when user does not have sufficient rights (not admin)', function (): void {
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn(
            [new AccessGroup(1, 'centreon_not_admin', 'not an admin')]
        );

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::notAllowed()->getMessage());
});

it('should present a NotFoundResponse when rule ID provided does not correspond to an existing rule', function (): void {
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
        ->method('exists')
        ->willReturn(false);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe((new NotFoundResponse('Resource access rule'))->getMessage());
});

it('should present a ErrorResponse when deletion goes wrong', function (): void {
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
        ->method('exists')
        ->willReturn(true);

    $this->writeRepository
        ->expects($this->once())
        ->method('deleteRuleAndDatasets')
        ->with(1)
        ->willThrowException(new \Exception());

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::errorWhileDeleting(new \Exception())->getMessage());
});

it('should present a NoContentResponse when deletion goes well', function (): void {
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
        ->method('exists')
        ->willReturn(true);

    $this->writeRepository
        ->expects($this->once())
        ->method('deleteRuleAndDatasets')
        ->with(1);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NoContentResponse::class);
});
