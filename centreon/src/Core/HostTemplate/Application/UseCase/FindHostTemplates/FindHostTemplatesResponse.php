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

namespace Core\HostTemplate\Application\UseCase\FindHostTemplates;

final class FindHostTemplatesResponse
{
    /** @var array<
     *     array{
     *          id: int,
     *          name: string,
     *          alias: string,
     *          snmpVersion: string|null,
     *          snmpCommunity: string,
     *          timezoneId: int|null,
     *          severityId: int|null,
     *          checkCommandId: int|null,
     *          checkCommandArgs: string,
     *          checkTimeperiodId: int|null,
     *          maxCheckAttempts: int|null,
     *          normalCheckInterval: int|null,
     *          retryCheckInterval: int|null,
     *          isActiveCheckEnabled: int,
     *          isPassiveCheckEnabled: int,
     *          isNotificationEnabled: int,
     *          notificationOptions: int,
     *          notificationInterval: int|null,
     *          notificationTimeperiodId: int|null,
     *          addInheritedContactGroup: bool,
     *          addInheritedContact: bool,
     *          firstNotificationDelay: int|null,
     *          recoveryNotificationDelay: int|null,
     *          acknowledgementTimeout: int|null,
     *          isFreshnessChecked: int,
     *          freshnessThreshold: int|null,
     *          isFlapDetectionEnabled: int,
     *          lowFlapThreshold: int|null,
     *          highFlapThreshold: int|null,
     *          isEventHandlerEnabled: int,
     *          eventHandlerCommandId: int|null,
     *          eventHandlerCommandArgs: string,
     *          noteUrl: string,
     *          note: string,
     *          actionUrl: string,
     *          iconId: int|null,
     *          iconAlternative: string,
     *          comment: string,
     *          isActivated: bool,
     *          isLocked: bool
     *     }
     * >
     */
    public array $hostTemplates = [];
}
