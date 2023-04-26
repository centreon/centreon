<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\ServiceCategory\Application\UseCase\FindServiceCategories;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\UseCase\FindServiceCategories\FindServiceCategories;
use Core\ServiceCategory\Application\UseCase\FindServiceCategories\FindServiceCategoriesResponse;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Exception;

beforeEach(function () {
    $this->serviceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->usecase = new FindServiceCategories(
        $this->serviceCategoryRepository,
        $this->accessGroupRepository,
        $this->requestParameters,
        $this->user
    );
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->serviceCategoryName = 'sc-name';
    $this->serviceCategoryAlias = 'sc-alias';
    $this->serviceCategory = new ServiceCategory(1, $this->serviceCategoryName, $this->serviceCategoryAlias);
    $this->responseArray = [
        'id' => 1,
        'name' => $this->serviceCategoryName,
        'alias' => $this->serviceCategoryAlias,
        'is_activated' => true,
    ];
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->serviceCategoryRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::findServiceCategories(new Exception())->getMessage());
});

it('should present a ForbiddenResponse when a non-admin user has unsufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, false],
            ]
        );

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceCategoryException::accessNotAllowed()->getMessage());
});

it('should present a FindServiceGroupsResponse when a non-admin user has read only rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, true],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, false],
            ]
        );
    $this->serviceCategoryRepository
        ->expects($this->once())
        ->method('findByRequestParameterAndAccessGroups')
        ->willReturn([$this->serviceCategory]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceCategoriesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceCategories[0])
        ->toBe($this->responseArray);
});

it('should present a FindServiceGroupsResponse when a non-admin user has read/write rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, true],
            ]
        );
    $this->serviceCategoryRepository
        ->expects($this->once())
        ->method('findByRequestParameterAndAccessGroups')
        ->willReturn([$this->serviceCategory]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceCategoriesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceCategories[0])
        ->toBe($this->responseArray);
});


it('should present a FindServiceCategoriesResponse with admin user', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->serviceCategoryRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willReturn([$this->serviceCategory]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceCategoriesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceCategories[0])
        ->toBe($this->responseArray);
});
