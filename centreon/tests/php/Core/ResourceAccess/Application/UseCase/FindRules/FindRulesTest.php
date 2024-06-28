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

namespace Tests\Core\ResourceAccess\Application\UseCase\FindRules;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\FindRules\FindRules;
use Core\ResourceAccess\Application\UseCase\FindRules\FindRulesResponse;
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
use Tests\Core\ResourceAccess\Infrastructure\API\FindRules\FindRulesPresenterStub;

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
        $this->filterTypes[] = new $className();
    }

    $this->datasetValidator = new DatasetFilterValidator(new \ArrayObject($this->filterTypes));

    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->repository = $this->createMock(ReadResourceAccessRepositoryInterface::class);
    $this->presenter = new FindRulesPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);

    $this->useCase = new FindRules($this->user, $this->repository, $this->requestParameters, $this->accessGroupRepository, true);
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

    ($this->useCase)($this->presenter);

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

    ($this->useCase)($this->presenter);

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
        ->method('findAllByRequestParameters')
        ->with($this->requestParameters)
        ->willThrowException($exception);

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(RuleException::errorWhileSearchingRules()->getMessage());
});

it('should present an ErrorResponse when an error occurs concerning the request parameters', function (): void {
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
        ->method('findAllByRequestParameters')
        ->with($this->requestParameters)
        ->willThrowException(new RequestParametersTranslatorException());

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});

it('should present a FindRulesResponse when no error occurs', function (): void {
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

    $rule = new Rule(
        id: 1,
        name: 'name',
        description: 'description',
        linkedContacts: [1],
        linkedContactGroups: [2],
        datasets: [new DatasetFilter('host', [3, 4], $this->datasetValidator)],
        isEnabled: true
    );

    $rulesFound = [$rule];
    $this->repository
        ->expects($this->once())
        ->method('findAllByRequestParameters')
        ->with($this->requestParameters)
        ->willReturn($rulesFound);

    ($this->useCase)($this->presenter);
    $response = $this->presenter->response;
    expect($response)->toBeInstanceOf(FindRulesResponse::class)
        ->and($response->rulesDto[0]->id)->toBe($rule->getId())
        ->and($response->rulesDto[0]->name)->toBe($rule->getName())
        ->and($response->rulesDto[0]->description)->toBe($rule->getDescription())
        ->and($response->rulesDto[0]->isEnabled)->toBe($rule->isEnabled());
});

