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

namespace Core\HostTemplate\Application\UseCase\AddHostTemplate;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\OptionService;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\CommandType;
use Core\Common\Domain\YesNoDefault;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateRequest;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\NewHostTemplate;
use Core\HostTemplate\Infrastructure\API\AddHostTemplate\AddHostTemplatePresenterOnPrem;
use Core\HostTemplate\Infrastructure\API\AddHostTemplate\AddHostTemplatePresenterSaas;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddHostTemplate
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadTimezoneRepositoryInterface $readTimezoneRepository,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param AddHostTemplateRequest $request
     * @param AddHostTemplatePresenterSaas|AddHostTemplatePresenterOnPrem $presenter
     */
    public function __invoke(AddHostTemplateRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostTemplateException::addNotAllowed()->getMessage())
                );

                return;
            }

            $this->assertIsValidName($request->name);
            $this->assertIsValidSeverity($request->severityId);
            $this->assertIsValidTimezone($request->timezoneId);
            $this->assertIsValidTimePeriod($request->checkTimeperiodId, 'checkTimeperiodId');
            $this->assertIsValidTimePeriod($request->notificationTimeperiodId, 'notificationTimeperiodId');
            $this->assertIsValidCommand($request->checkCommandId, CommandType::Check, 'checkCommandId');
            $this->assertIsValidCommand($request->eventHandlerCommandId, CommandType::Check, 'eventHandlerCommandId');
            $this->assertIsValidIcon($request->iconId);

            $newHostTemplate = $this->createNewHostTemplate($request);

            $hostTemplateId = $this->writeHostTemplateRepository->add($newHostTemplate);

            $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);

            if (! $hostTemplate) {
                $presenter->setResponseStatus(
                    new ErrorResponse(HostTemplateException::errorWhileRetrievingObject())
                );

                return;
            }

            $presenter->present(
                new CreatedResponse($hostTemplateId, $this->createResponse($hostTemplate))
            );
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostTemplateException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    HostTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostTemplateException::addHostTemplate($ex))
            );
            $this->error((string) $ex);
        }
    }

    /**
     * Assert name is not already used.
     *
     * @param string $name
     *
     * @throws HostTemplateException
     */
    private function assertIsValidName(string $name): void
    {
        $formatedName = HostTemplate::formatName($name);
        if ($this->readHostTemplateRepository->existsByName($formatedName)) {
            $this->error('Host template name already exists', ['name' => $name, 'formatedName' => $formatedName]);

            throw HostTemplateException::nameAlreadyExists($formatedName, $name);
        }
    }

    /**
     * Assert icon ID is valid.
     *
     * @param int $iconId
     *
     * @throws HostTemplateException
     */
    private function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && false === $this->readViewImgRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw HostTemplateException::idDoesNotExist('iconId', $iconId);
        }
    }

    /**
     * Assert time period ID is valid.
     *
     * @param int $timePeriodId
     * @param ?string $propertieName
     *
     * @throws HostTemplateException
     */
    private function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertieName = null): void
    {
        if ($timePeriodId !== null && false === $this->readTimePeriodRepository->exists($timePeriodId) ) {
            $this->error('Time period does not exist', ['time_period_id' => $timePeriodId]);

            throw HostTemplateException::idDoesNotExist($propertieName ?? 'timePeriodId', $timePeriodId);
        }
    }

    /**
     * Assert host severity ID is valid.
     *
     * @param int $severityId
     *
     * @throws HostTemplateException
     */
    private function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null && false === $this->readHostSeverityRepository->exists($severityId) ) {
            $this->error('Host severity does not exist', ['severity_id' => $severityId]);

            throw HostTemplateException::idDoesNotExist('severityId', $severityId);
        }
    }

    /**
     * Assert timezone ID is valid.
     *
     * @param int $timezone
     * @param int $timezoneId
     *
     * @throws HostTemplateException
     */
    private function assertIsValidTimezone(?int $timezoneId): void
    {
        if ($timezoneId !== null && false === $this->readTimezoneRepository->exists($timezoneId) ) {
            $this->error('Timezone does not exist', ['timezone_id' => $timezoneId]);

            throw HostTemplateException::idDoesNotExist('timezoneId', $timezoneId);
        }
    }

    /**
     * Assert command ID is valid.
     *
     * @param int $commandId
     * @param CommandType $commandType
     * @param ?string $propertieName
     *
     * @throws HostTemplateException
     */
    private function assertIsValidCommand(?int $commandId, CommandType $commandType, ?string $propertieName = null): void
    {
        if (
            $commandId !== null
            && false === $this->readCommandRepository->existsByIdAndCommandType($commandId, $commandType)
        ) {
            $this->error('Command does not exist', ['command_id' => $commandId, 'command_type' => $commandType]);

            throw HostTemplateException::idDoesNotExist($propertieName ?? 'commandId', $commandId);
        }
    }

    private function createNewHostTemplate(AddHostTemplateRequest $request): NewHostTemplate
    {
        $inheritanceMode
            = ($this->optionService->findSelectedOptions(['inheritance_mode']))['inheritance_mode']?->getValue();

        return new NewHostTemplate(
            $request->name,
            $request->alias,
            $request->snmpVersion === ''
                ? null
                : SnmpVersion::from($request->snmpVersion),
            $request->snmpCommunity,
            $request->timezoneId,
            $request->severityId,
            $request->checkCommandId,
           $request->checkCommandArgs,
            $request->checkTimeperiodId,
            $request->maxCheckAttempts,
            $request->normalCheckInterval,
            $request->retryCheckInterval,
            $request->activeCheckEnabled === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->activeCheckEnabled),
            $request->passiveCheckEnabled === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->passiveCheckEnabled),
            $request->notificationEnabled === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->notificationEnabled),
            $request->notificationOptions === null
                ? []
                : HostEventConverter::fromBitMask($request->notificationOptions),
            $request->notificationInterval,
            $request->notificationTimeperiodId,
            $inheritanceMode === '1' ? $request->addInheritedContactGroup : false,
            $inheritanceMode === '1' ? $request->addInheritedContact : false,
            $request->firstNotificationDelay,
            $request->recoveryNotificationDelay,
            $request->acknowledgementTimeout,
            $request->freshnessChecked === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->freshnessChecked),
            $request->freshnessThreshold,
            $request->flapDetectionEnabled === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->flapDetectionEnabled),
            $request->lowFlapThreshold,
            $request->highFlapThreshold,
            $request->eventHandlerEnabled === ''
                ? YesNoDefault::Default
                : YesNoDefaultConverter::fromScalar($request->eventHandlerEnabled),
            $request->eventHandlerCommandId,
            $request->eventHandlerCommandArgs,
            $request->noteUrl,
            $request->note,
            $request->actionUrl,
            $request->iconId,
            $request->iconAlternative,
            $request->comment,
            $request->isActivated,
        );
    }

    private function createResponse(?HostTemplate $hostTemplate): AddHostTemplateResponse
    {
        $response = new AddHostTemplateResponse();
        if ($hostTemplate !== null) {
                $response->id = $hostTemplate->getId();
                $response->name = $hostTemplate->getName();
                $response->alias = $hostTemplate->getAlias();
                $response->snmpVersion = $hostTemplate->getSnmpVersion()? $hostTemplate->getSnmpVersion()->value : null;
                $response->snmpCommunity = $hostTemplate->getSnmpCommunity();
                $response->timezoneId = $hostTemplate->getTimezoneId();
                $response->severityId = $hostTemplate->getSeverityId();
                $response->checkCommandId = $hostTemplate->getCheckCommandId();
                $response->checkCommandArgs = $hostTemplate->getCheckCommandArgs();
                $response->checkTimeperiodId = $hostTemplate->getCheckTimeperiodId();
                $response->maxCheckAttempts = $hostTemplate->getMaxCheckAttempts();
                $response->normalCheckInterval = $hostTemplate->getNormalCheckInterval();
                $response->retryCheckInterval = $hostTemplate->getretryCheckInterval();
                $response->activeCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getActiveCheckEnabled());
                $response->passiveCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getPassiveCheckEnabled());
                $response->notificationEnabled = YesNoDefaultConverter::toInt($hostTemplate->getNotificationEnabled());
                $response->notificationOptions = HostEventConverter::toBitmask($hostTemplate->getNotificationOptions());
                $response->notificationInterval = $hostTemplate->getNotificationInterval();
                $response->notificationTimeperiodId = $hostTemplate->getNotificationTimeperiodId();
                $response->addInheritedContactGroup = $hostTemplate->addInheritedContactGroup();
                $response->addInheritedContact = $hostTemplate->addInheritedContact();
                $response->firstNotificationDelay = $hostTemplate->getfirstNotificationDelay();
                $response->recoveryNotificationDelay = $hostTemplate->getrecoveryNotificationDelay();
                $response->acknowledgementTimeout = $hostTemplate->getAcknowledgementTimeout();
                $response->freshnessChecked = YesNoDefaultConverter::toInt($hostTemplate->getFreshnessChecked());
                $response->freshnessThreshold = $hostTemplate->getfreshnessThreshold();
                $response->flapDetectionEnabled = YesNoDefaultConverter::toInt($hostTemplate->getFlapDetectionEnabled());
                $response->lowFlapThreshold = $hostTemplate->getLowFlapThreshold();
                $response->highFlapThreshold = $hostTemplate->getHighFlapThreshold();
                $response->eventHandlerEnabled = YesNoDefaultConverter::toInt($hostTemplate->getEventHandlerEnabled());
                $response->eventHandlerCommandId = $hostTemplate->getEventHandlerCommandId();
                $response->eventHandlerCommandArgs = $hostTemplate->getEventHandlerCommandArgs();
                $response->noteUrl = $hostTemplate->getNoteUrl();
                $response->note = $hostTemplate->getNote();
                $response->actionUrl = $hostTemplate->getActionUrl();
                $response->iconId = $hostTemplate->getIconId();
                $response->iconAlternative = $hostTemplate->getIconAlternative();
                $response->comment = $hostTemplate->getComment();
                $response->isActivated = $hostTemplate->isActivated();
                $response->isLocked = $hostTemplate->isLocked();
        }

        return $response;
    }
}
