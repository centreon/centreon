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
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\CommandType;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\NewHostTemplate;
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
     * @param AddHostTemplatePresenterInterface $presenter
     */
    public function __invoke(AddHostTemplateRequest $request, AddHostTemplatePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a host template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
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
            $this->assertIsValidCommand($request->eventHandlerCommandId, null, 'eventHandlerCommandId');
            $this->assertIsValidIcon($request->iconId);

            $newHostTemplate = $this->createNewHostTemplate($request);

            $hostTemplateId = $this->writeHostTemplateRepository->add($newHostTemplate);

            $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);

            if (! $hostTemplate) {
                $presenter->presentResponse(
                    new ErrorResponse(HostTemplateException::errorWhileRetrievingObject())
                );

                return;
            }

            $presenter->presentResponse($this->createResponse($hostTemplate));
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostTemplateException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    HostTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(HostTemplateException::addHostTemplate())
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
        $formattedName = HostTemplate::formatName($name);
        if ($this->readHostTemplateRepository->existsByName($formattedName)) {
            $this->error('Host template name already exists', ['name' => $name, 'formattedName' => $formattedName]);

            throw HostTemplateException::nameAlreadyExists($formattedName, $name);
        }
    }

    /**
     * Assert icon ID is valid.
     *
     * @param ?int $iconId
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
     * @param ?int $timePeriodId
     * @param ?string $propertyName
     *
     * @throws HostTemplateException
     */
    private function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertyName = null): void
    {
        if ($timePeriodId !== null && false === $this->readTimePeriodRepository->exists($timePeriodId) ) {
            $this->error('Time period does not exist', ['time_period_id' => $timePeriodId]);

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'timePeriodId', $timePeriodId);
        }
    }

    /**
     * Assert host severity ID is valid.
     *
     * @param ?int $severityId
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
     * @param ?int $timezoneId
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
     * @param ?int $commandId
     * @param ?CommandType $commandType
     * @param ?string $propertyName
     *
     * @throws HostTemplateException
     */
    private function assertIsValidCommand(?int $commandId, ?CommandType $commandType = null, ?string $propertyName = null): void
    {
        if ($commandId === null) {
            return;
        }

        if ($commandType === null && false === $this->readCommandRepository->exists($commandId)) {
            $this->error('Command does not exist', ['command_id' => $commandId]);

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
        if (
            $commandType !== null
            && false === $this->readCommandRepository->existsByIdAndCommandType($commandId, $commandType)
        ) {
            $this->error('Command does not exist', ['command_id' => $commandId, 'command_type' => $commandType]);

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
    }

    private function createNewHostTemplate(AddHostTemplateRequest $request): NewHostTemplate
    {
        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode['inheritance_mode'])
            ? $inheritanceMode['inheritance_mode']->getValue()
            : 0;

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
            YesNoDefaultConverter::fromScalar($request->activeCheckEnabled),
            YesNoDefaultConverter::fromScalar($request->passiveCheckEnabled),
            YesNoDefaultConverter::fromScalar($request->notificationEnabled),
            $request->notificationOptions === null
                ? []
                : HostEventConverter::fromBitFlag($request->notificationOptions),
            $request->notificationInterval,
            $request->notificationTimeperiodId,
            $inheritanceMode === '1' ? $request->addInheritedContactGroup : false,
            $inheritanceMode === '1' ? $request->addInheritedContact : false,
            $request->firstNotificationDelay,
            $request->recoveryNotificationDelay,
            $request->acknowledgementTimeout,
            YesNoDefaultConverter::fromScalar($request->freshnessChecked),
            $request->freshnessThreshold,
            YesNoDefaultConverter::fromScalar($request->flapDetectionEnabled),
            $request->lowFlapThreshold,
            $request->highFlapThreshold,
            YesNoDefaultConverter::fromScalar($request->eventHandlerEnabled),
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
                $response->snmpVersion = $hostTemplate->getSnmpVersion()?->value;
                $response->snmpCommunity = $hostTemplate->getSnmpCommunity();
                $response->timezoneId = $hostTemplate->getTimezoneId();
                $response->severityId = $hostTemplate->getSeverityId();
                $response->checkCommandId = $hostTemplate->getCheckCommandId();
                $response->checkCommandArgs = $hostTemplate->getCheckCommandArgs();
                $response->checkTimeperiodId = $hostTemplate->getCheckTimeperiodId();
                $response->maxCheckAttempts = $hostTemplate->getMaxCheckAttempts();
                $response->normalCheckInterval = $hostTemplate->getNormalCheckInterval();
                $response->retryCheckInterval = $hostTemplate->getRetryCheckInterval();
                $response->activeCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getActiveCheckEnabled());
                $response->passiveCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getPassiveCheckEnabled());
                $response->notificationEnabled = YesNoDefaultConverter::toInt($hostTemplate->getNotificationEnabled());
                $response->notificationOptions = HostEventConverter::toBitFlag($hostTemplate->getNotificationOptions());
                $response->notificationInterval = $hostTemplate->getNotificationInterval();
                $response->notificationTimeperiodId = $hostTemplate->getNotificationTimeperiodId();
                $response->addInheritedContactGroup = $hostTemplate->addInheritedContactGroup();
                $response->addInheritedContact = $hostTemplate->addInheritedContact();
                $response->firstNotificationDelay = $hostTemplate->getFirstNotificationDelay();
                $response->recoveryNotificationDelay = $hostTemplate->getRecoveryNotificationDelay();
                $response->acknowledgementTimeout = $hostTemplate->getAcknowledgementTimeout();
                $response->freshnessChecked = YesNoDefaultConverter::toInt($hostTemplate->getFreshnessChecked());
                $response->freshnessThreshold = $hostTemplate->getFreshnessThreshold();
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
