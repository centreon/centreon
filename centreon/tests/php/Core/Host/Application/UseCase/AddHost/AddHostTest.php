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

namespace Tests\Core\ServiceSeverity\Application\UseCase\AddServiceSeverity;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Application\UseCase\AddHost\AddHost;
use Core\Host\Application\UseCase\AddHost\AddHostRequest;
use Core\Host\Application\UseCase\AddHost\AddHostResponse;
use Core\Host\Application\UseCase\AddHost\AddHostValidation;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use phpDocumentor\Reflection\Types\This;
use Tests\Core\Host\Infrastructure\API\AddHost\AddHostPresenterStub;

beforeEach(function (): void {
    $this->presenter = new AddHostPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new AddHost(
        writeHostRepository: $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class),
        readHostRepository: $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        writeMonitoringServerRepository: $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        readHostTemplateRepository: $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        readHostCategoryRepository: $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        readHostGroupRepository: $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        writeHostCategoryRepository: $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class),
        writeHostGroupRepository: $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readHostMacroRepository: $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        readCommandMacroRepository: $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        writeHostMacroRepository: $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        optionService: $this->optionService = $this->createMock(OptionService::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        validation: $this->validation = $this->createMock(AddHostValidation::class),
        writeVaultRepository: $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        readVaultRepository: $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');

    // Settup host
    $this->request = new AddHostRequest();
    $this->request->monitoringServerId = 1;
    $this->request->name = '  host name  ';
    $this->request->address = '127.0.0.1';
    $this->request->snmpVersion = SnmpVersion::Two->value;
    $this->request->geoCoordinates = '48.1,12.20';
    $this->request->notificationOptions = HostEventConverter::toBitFlag([HostEvent::Down, HostEvent::Unreachable]);
    $this->request->alias = '  host-alias  ';
    $this->request->snmpCommunity = 'snmpCommunity-value';
    $this->request->noteUrl = 'noteUrl-value';
    $this->request->note = 'note-value';
    $this->request->actionUrl = 'actionUrl-value';
    $this->request->iconAlternative = 'iconAlternative-value';
    $this->request->comment = 'comment-value';
    $this->request->checkCommandArgs = ['arg1', 'arg2'];
    $this->request->eventHandlerCommandArgs = ['arg3', '  arg4'];
    $this->request->timezoneId = 1;
    $this->request->severityId = 1;
    $this->request->checkCommandId = 1;
    $this->request->checkTimeperiodId = 1;
    $this->request->maxCheckAttempts = 5;
    $this->request->normalCheckInterval = 5;
    $this->request->retryCheckInterval = 5;
    $this->request->notificationInterval = 5;
    $this->request->notificationTimeperiodId = 2;
    $this->request->eventHandlerCommandId = 2;
    $this->request->iconId = 1;
    $this->request->firstNotificationDelay = 5;
    $this->request->recoveryNotificationDelay = 5;
    $this->request->acknowledgementTimeout = 5;
    $this->request->freshnessThreshold = 5;
    $this->request->lowFlapThreshold = 5;
    $this->request->highFlapThreshold = 5;
    $this->request->activeCheckEnabled = 1;
    $this->request->passiveCheckEnabled = 1;
    $this->request->notificationEnabled = 1;
    $this->request->freshnessChecked = 1;
    $this->request->flapDetectionEnabled = 1;
    $this->request->eventHandlerEnabled = 1;
    $this->request->addInheritedContactGroup = true;
    $this->request->addInheritedContact = true;
    $this->request->isActivated = false;

    $this->host = new Host(
        id: 1,
        monitoringServerId: $this->request->monitoringServerId,
        name: $this->request->name,
        address: $this->request->address,
        snmpVersion: SnmpVersion::from($this->request->snmpVersion),
        geoCoordinates: GeoCoords::fromString($this->request->geoCoordinates),
        alias: $this->request->alias,
        snmpCommunity: $this->request->snmpCommunity,
        noteUrl: $this->request->noteUrl,
        note: $this->request->note,
        actionUrl: $this->request->actionUrl,
        iconAlternative: $this->request->iconAlternative,
        comment: $this->request->comment,
        checkCommandArgs: ['arg1', 'test2'], // $this->request->checkCommandArgs,
        eventHandlerCommandArgs: $this->request->eventHandlerCommandArgs,
        notificationOptions: HostEventConverter::fromBitFlag($this->request->notificationOptions),
        timezoneId: $this->request->timezoneId,
        severityId: $this->request->severityId,
        checkCommandId: $this->request->checkCommandId,
        checkTimeperiodId: $this->request->checkTimeperiodId,
        notificationTimeperiodId: $this->request->notificationTimeperiodId,
        eventHandlerCommandId: $this->request->eventHandlerCommandId,
        iconId: $this->request->iconId,
        maxCheckAttempts: $this->request->maxCheckAttempts,
        normalCheckInterval: $this->request->normalCheckInterval,
        retryCheckInterval: $this->request->retryCheckInterval,
        notificationInterval: $this->request->notificationInterval,
        firstNotificationDelay: $this->request->firstNotificationDelay,
        recoveryNotificationDelay: $this->request->recoveryNotificationDelay,
        acknowledgementTimeout: $this->request->acknowledgementTimeout,
        freshnessThreshold: $this->request->freshnessThreshold,
        lowFlapThreshold: $this->request->lowFlapThreshold,
        highFlapThreshold: $this->request->highFlapThreshold,
        activeCheckEnabled: YesNoDefaultConverter::fromScalar($this->request->activeCheckEnabled),
        passiveCheckEnabled: YesNoDefaultConverter::fromScalar($this->request->passiveCheckEnabled),
        notificationEnabled: YesNoDefaultConverter::fromScalar($this->request->notificationEnabled),
        freshnessChecked: YesNoDefaultConverter::fromScalar($this->request->freshnessChecked),
        flapDetectionEnabled: YesNoDefaultConverter::fromScalar($this->request->flapDetectionEnabled),
        eventHandlerEnabled: YesNoDefaultConverter::fromScalar($this->request->eventHandlerEnabled),
        addInheritedContactGroup: $this->request->addInheritedContactGroup,
        addInheritedContact: $this->request->addInheritedContact,
        isActivated: $this->request->isActivated,
    );

    // Settup categories
    $this->categories = [
        $this->categoryA = new HostCategory(12, 'cat-name-A', 'cat-alias-A'),
        $this->categoryB = new HostCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];
    $this->request->categories = [$this->categoryA->getId(), $this->categoryB->getId()];

    // Settup groups
    $this->groups = [
        $this->groupA = new HostGroup(6, 'grp-name-A', 'grp-alias-A', '', '', '', null, null, null, null, '', true),
        $this->groupB = new HostGroup(7, 'grp-name-B', 'grp-alias-B', '', '', '', null, null, null, null, '', true),
    ];
    $this->request->groups = [$this->groupA->getId(), $this->groupB->getId()];

    // Settup parent templates
    $this->request->templates = [4, 8];
    $this->parentTemplates = [
        ['id' => 4, 'name' => 'template-A'],
        ['id' => 8, 'name' => 'template-B'],
    ];

    // Settup macros
    $this->macroA = new Macro($this->host->getId(), 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new Macro($this->host->getId(), 'macroNameB', 'macroValueB');
    $this->macroB->setOrder(1);
    $this->commandMacro = new CommandMacro(1, CommandMacroType::Host, 'commandMacroName');
    $this->commandMacros = [
        $this->commandMacro->getName() => $this->commandMacro,
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];
    $this->inheritanceInfos = [
        ['parent_id' => 4, 'child_id' => 1, 'order' => 1],
        ['parent_id' => 8, 'child_id' => 1, 'order' => 2],
    ];
    $this->request->macros = [
        [
            'name' => $this->macroA->getName(),
            'value' => $this->macroA->getValue(),
            'is_password' => $this->macroA->isPassword(),
            'description' => $this->macroA->getDescription(),
        ],
        [
            'name' => $this->macroB->getName(),
            'value' => $this->macroB->getValue(),
            'is_password' => $this->macroB->isPassword(),
            'description' => $this->macroB->getDescription(),
        ],
    ];
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::addHost()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::addNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->willThrowException(
            HostException::nameAlreadyExists(
                Host::formatName($this->request->name),
                $this->request->name
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::nameAlreadyExists(
                Host::formatName($this->request->name),
                $this->request->name
            )->getMessage()
        );
});

it('should present a ConflictResponse when monitoring server ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidMonitoringServer')
        ->willThrowException(
            HostException::idDoesNotExist('monitoringServerId', $this->request->monitoringServerId)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idDoesNotExist('monitoringServerId', $this->request->monitoringServerId)->getMessage());
});

it('should present a ConflictResponse when host severity ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(
            HostException::idDoesNotExist('severityId', $this->request->severityId)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idDoesNotExist('severityId', $this->request->severityId)->getMessage());
});

it('should present a ConflictResponse when a host timezone ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimezone')
        ->willThrowException(
            HostException::idDoesNotExist('timezoneId', $this->request->timezoneId)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idDoesNotExist('timezoneId', $this->request->timezoneId)->getMessage());
});

it('should present a ConflictResponse when a timeperiod ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            HostException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when a command ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(
            HostException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the host icon ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            HostException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )->getMessage()
        );
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->request->name = '';

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(AssertionException::notEmptyString('NewHost::name')->getMessage());
});

it('should present a ConflictResponse when a host category ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories')
        ->willThrowException(
            HostException::idsDoNotExist(
                'categories',
                [$this->request->categories[1]]
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idsDoNotExist(
                'categories',
                [$this->request->categories[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when a host group ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation
        ->expects($this->once())
        ->method('assertAreValidGroups')
        ->willThrowException(
            HostException::idsDoNotExist(
                'groups',
                [$this->request->groups[1]]
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idsDoNotExist(
                'groups',
                [$this->request->groups[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when a parent template ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(
            HostException::idsDoNotExist('templates', $this->request->templates)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idsDoNotExist(
                'templates',
                 $this->request->templates
            )->getMessage()
        );
});

it('should present an ErrorResponse if the newly created host cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->writeHostRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success (with admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validation->expects($this->once())->method('assertIsValidMonitoringServer');
    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->host->getId());

    $this->validation->expects($this->once())->method('assertAreValidCategories');
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidGroups');
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->readHostRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritanceInfos);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn([]);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeHostMacroRepository
        ->expects($this->exactly(2))
        ->method('add');
    $this->writeMonitoringServerRepository
        ->expects($this->once())
        ->method('notifyConfigurationChange');

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->host);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->categories);
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->groups);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn(
            array_combine(
                array_map((fn($row) => $row['id']), $this->parentTemplates),
                array_map((fn($row) => $row['name']), $this->parentTemplates)
            )
        );
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->hostMacros);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(AddHostResponse::class)
        ->and($response->id)
        ->toBe($this->host->getId())
        ->and($response->monitoringServerId)
        ->toBe($this->host->getMonitoringServerId())
        ->and($response->name)
        ->toBe($this->host->getName())
        ->and($response->address)
        ->toBe($this->host->getAddress())
        ->and($response->snmpVersion)
        ->toBe($this->host->getSnmpVersion()->value)
        ->and($response->geoCoords)
        ->toBe($this->host->getGeoCoordinates()?->__toString())
        ->and($response->alias)
        ->toBe($this->host->getAlias())
        ->and($response->snmpCommunity)
        ->toBe($this->host->getSnmpCommunity())
        ->and($response->noteUrl)
        ->toBe($this->host->getNoteUrl())
        ->and($response->note)
        ->toBe($this->host->getNote())
        ->and($response->actionUrl)
        ->toBe($this->host->getActionUrl())
        ->and($response->iconAlternative)
        ->toBe($this->host->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->host->getComment())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->host->getEventHandlerCommandArgs())
        ->and($response->checkCommandArgs)
        ->toBe($this->host->getCheckCommandArgs())
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->host->getNotificationOptions()))
        ->and($response->timezoneId)
        ->toBe($this->host->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->host->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->host->getCheckCommandId())
        ->and($response->checkTimeperiodId)
        ->toBe($this->host->getCheckTimeperiodId())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->host->getNotificationTimeperiodId())
        ->and($response->eventHandlerCommandId)
        ->toBe($this->host->getEventHandlerCommandId())
        ->and($response->iconId)
        ->toBe($this->host->getIconId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->host->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->host->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->host->getRetryCheckInterval())
        ->and($response->notificationInterval)
        ->toBe($this->host->getNotificationInterval())
        ->and($response->firstNotificationDelay)
        ->toBe($this->host->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->host->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->host->getAcknowledgementTimeout())
        ->and($response->freshnessThreshold)
        ->toBe($this->host->getFreshnessThreshold())
        ->and($response->lowFlapThreshold)
        ->toBe($this->host->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->host->getHighFlapThreshold())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getNotificationEnabled()))
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFreshnessChecked()))
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFlapDetectionEnabled()))
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getEventHandlerEnabled()))
        ->and($response->categories)
        ->toBe(array_map(
            (fn($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->groups)
        ->toBe(array_map(
            (fn($group) => ['id' => $group->getId(), 'name' => $group->getName()]),
            $this->groups
        ))
        ->and($response->templates)
        ->toBe(array_map(
            (fn($template) => ['id' => $template['id'], 'name' => $template['name']]),
            $this->parentTemplates
        ))
        ->and($response->macros)
        ->toBe(array_map(
            (fn($macro) => [
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->hostMacros
        ))
        ->and($response->addInheritedContactGroup)
        ->toBe($this->host->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->host->addInheritedContact())
        ->and($response->isActivated)
        ->toBe($this->host->isActivated());
});

it('should return created object on success (with non-admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validation->expects($this->once())->method('assertIsValidMonitoringServer');
    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->host->getId());

    $this->validation->expects($this->once())->method('assertAreValidCategories');
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidGroups');
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->readHostRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritanceInfos);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn([]);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeHostMacroRepository
        ->expects($this->exactly(2))
        ->method('add');
    $this->writeMonitoringServerRepository
        ->expects($this->once())
        ->method('notifyConfigurationChange');

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->host);
    $this->readAccessGroupRepository
        ->expects($this->any())
        ->method('findByContact');
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->categories);
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->groups);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn(
            array_combine(
                array_map((fn($row) => $row['id']), $this->parentTemplates),
                array_map((fn($row) => $row['name']), $this->parentTemplates)
            )
        );
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->hostMacros);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(AddHostResponse::class)
        ->and($response->id)
        ->toBe($this->host->getId())
        ->and($response->monitoringServerId)
        ->toBe($this->host->getMonitoringServerId())
        ->and($response->name)
        ->toBe($this->host->getName())
        ->and($response->address)
        ->toBe($this->host->getAddress())
        ->and($response->snmpVersion)
        ->toBe($this->host->getSnmpVersion()->value)
        ->and($response->geoCoords)
        ->toBe($this->host->getGeoCoordinates()?->__toString())
        ->and($response->alias)
        ->toBe($this->host->getAlias())
        ->and($response->snmpCommunity)
        ->toBe($this->host->getSnmpCommunity())
        ->and($response->noteUrl)
        ->toBe($this->host->getNoteUrl())
        ->and($response->note)
        ->toBe($this->host->getNote())
        ->and($response->actionUrl)
        ->toBe($this->host->getActionUrl())
        ->and($response->iconAlternative)
        ->toBe($this->host->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->host->getComment())
        ->and($response->checkCommandArgs)
        ->toBe($this->host->getCheckCommandArgs())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->host->getEventHandlerCommandArgs())
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->host->getNotificationOptions()))
        ->and($response->timezoneId)
        ->toBe($this->host->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->host->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->host->getCheckCommandId())
        ->and($response->checkTimeperiodId)
        ->toBe($this->host->getCheckTimeperiodId())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->host->getNotificationTimeperiodId())
        ->and($response->eventHandlerCommandId)
        ->toBe($this->host->getEventHandlerCommandId())
        ->and($response->iconId)
        ->toBe($this->host->getIconId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->host->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->host->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->host->getRetryCheckInterval())
        ->and($response->notificationInterval)
        ->toBe($this->host->getNotificationInterval())
        ->and($response->firstNotificationDelay)
        ->toBe($this->host->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->host->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->host->getAcknowledgementTimeout())
        ->and($response->freshnessThreshold)
        ->toBe($this->host->getFreshnessThreshold())
        ->and($response->lowFlapThreshold)
        ->toBe($this->host->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->host->getHighFlapThreshold())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getNotificationEnabled()))
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFreshnessChecked()))
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFlapDetectionEnabled()))
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getEventHandlerEnabled()))
        ->and($response->categories)
        ->toBe(array_map(
            (fn($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->groups)
        ->toBe(array_map(
            (fn($group) => ['id' => $group->getId(), 'name' => $group->getName()]),
            $this->groups
        ))
        ->and($response->templates)
        ->toBe(array_map(
            (fn($template) => ['id' => $template['id'], 'name' => $template['name']]),
            $this->parentTemplates
        ))
        ->and($response->macros)
        ->toBe(array_map(
            (fn($macro) => [
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->hostMacros
        ))
        ->and($response->addInheritedContactGroup)
        ->toBe($this->host->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->host->addInheritedContact())
        ->and($response->isActivated)
        ->toBe($this->host->isActivated());
    });
