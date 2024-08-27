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

namespace Tests\Core\Host\Application\UseCase\FindHosts;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\UseCase\FindHosts\FindHosts;
use Core\Host\Application\UseCase\FindHosts\FindHostsResponse;
use Core\Host\Domain\Model\SmallHost;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Tests\Core\Host\Infrastructure\API\FindHosts\FindHostsPresenterStub;

beforeEach(closure: function (): void {
   $this->requestParameters = $this->createMock(RequestParametersInterface::class);
   $this->user = $this->createMock(ContactInterface::class);
   $this->hostRepository = $this->createMock(ReadHostRepositoryInterface::class);
   $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
   $this->hostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
   $this->hostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
   $this->hostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class);
   $this->presenter = new FindHostsPresenterStub($this->createMock(PresenterFormatterInterface::class));

   $this->useCase = new FindHosts(
       $this->requestParameters,
       $this->user,
       $this->hostRepository,
       $this->accessGroupRepository,
       $this->hostCategoryRepository,
       $this->hostTemplateRepository,
       $this->hostGroupRepository,
       false
   );
});

it('should present a Forbidden response when a non-admin user does not have rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_WRITE, false],
            ]
        );

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::listingNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an error occurs concerning the request parameters', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_CONFIGURATION_HOSTS_READ)
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willThrowException(new RequestParametersTranslatorException());

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});

it('should present an ErrorResponse when an exception occurs', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_CONFIGURATION_HOSTS_READ)
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $exception = new \Exception();
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willThrowException($exception);

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::errorWhileSearchingForHosts($exception)->getMessage());
});

it('should present a FindHostsResponse when no error occurs for an admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_CONFIGURATION_HOSTS_READ)
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $oneHost = new SmallHost(
        1,
        new TrimmedString('my_host'),
        new TrimmedString('my_alias'),
        new TrimmedString('127.0.0.1'),
        1,
        2,
        true,
        new SimpleEntity(1, new TrimmedString('poller1'), 'Host'),
        new SimpleEntity(10, new TrimmedString('24x7'), 'Host'),
        new SimpleEntity(20, new TrimmedString('none'), 'Host'),
        new SimpleEntity(30, new TrimmedString('sev1'), 'Host'),
    );
    $oneHost->addTemplateId(12);
    $oneHost->addGroupId(33);
    $oneHost->addCategoryId(44);

    $hostsFound = [$oneHost];
    $this->hostRepository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->with($this->requestParameters)
        ->willReturn($hostsFound);

    $hostGroup = new HostGroup(
        $oneHost->getGroupIds()[0],
        'hostgroup_name',
        '',
        '',
        '',
        '',
        null,
        null,
        null,
        null,
        '',
        true,
    );
    $this->hostGroupRepository
        ->expects($this->once())
        ->method('findByIds')
        ->with(...$oneHost->getGroupIds())
        ->willReturn([$hostGroup]);

    $hostTemplate = new HostTemplate($oneHost->getTemplateIds()[0], 'htpl_name', 'htpl_alias');
    $this->hostTemplateRepository
        ->expects($this->once())
        ->method('findByIds')
        ->with(...$oneHost->getTemplateIds())
        ->willReturn([$hostTemplate]);

    $hostCategory = new HostCategory($oneHost->getCategoryIds()[0], 'cat1_name', 'cat1_alias');
    $this->hostCategoryRepository
        ->expects($this->once())
        ->method('findByIds')
        ->with(...$oneHost->getCategoryIds())
        ->willReturn([$hostCategory]);

    ($this->useCase)($this->presenter);
    $response = $this->presenter->response;
    expect($response)->toBeInstanceOf(FindHostsResponse::class)
        ->and($response->hostDto[0]->id)->toBe($oneHost->getId())
        ->and($response->hostDto[0]->name)->toBe((string) $oneHost->getName())
        ->and($response->hostDto[0]->alias)->toBe((string) $oneHost->getAlias())
        ->and($response->hostDto[0]->ipAddress)->toBe((string) $oneHost->getIpAddress())
        ->and($response->hostDto[0]->isActivated)->toBe($oneHost->isActivated())
        ->and($response->hostDto[0]->poller->id)->toBe($oneHost->getMonitoringServer()->getId())
        ->and($response->hostDto[0]->poller->name)->toBe($oneHost->getMonitoringServer()->getName())
        ->and($response->hostDto[0]->checkTimeperiod->id)->toBe($oneHost->getCheckTimePeriod()->getId())
        ->and($response->hostDto[0]->checkTimeperiod->name)->toBe($oneHost->getCheckTimePeriod()->getName())
        ->and($response->hostDto[0]->notificationTimeperiod->id)->toBe($oneHost->getNotificationTimePeriod()->getId())
        ->and($response->hostDto[0]->notificationTimeperiod->name)->toBe($oneHost->getNotificationTimePeriod()
        ->getName())
        ->and($response->hostDto[0]->retryCheckInterval)->toBe($oneHost->getRetryCheckInterval())
        ->and($response->hostDto[0]->normalCheckInterval)->toBe($oneHost->getNormalCheckInterval())
        ->and($response->hostDto[0]->severity->id)->toBe($oneHost->getSeverity()->getId())
        ->and($response->hostDto[0]->severity->name)->toBe($oneHost->getSeverity()->getName())
        ->and($response->hostDto[0]->groups[0]->id)->toBe($oneHost->getGroupIds()[0])
        ->and($response->hostDto[0]->groups[0]->name)->toBe($hostGroup->getName())
        ->and($response->hostDto[0]->templateParents[0]->id)->toBe($oneHost->getTemplateIds()[0])
        ->and($response->hostDto[0]->templateParents[0]->name)->toBe($hostTemplate->getName())
        ->and($response->hostDto[0]->categories[0]->id)->toBe($oneHost->getCategoryIds()[0])
        ->and($response->hostDto[0]->categories[0]->name)->toBe($hostCategory->getName());
});

it('should present a FindHostsResponse when no error occurs for a non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_CONFIGURATION_HOSTS_READ)
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $accessGroups = [new AccessGroup(1, 'acg_name', 'acg_alias')];
    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->hostRepository
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroups')
        ->with($this->requestParameters, $accessGroups)
        ->willReturn([]);

    ($this->useCase)($this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(FindHostsResponse::class)
        ->and($this->presenter->response->hostDto)->toHaveCount(0);
});
