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

namespace Core\HostTemplate\Infrastructure\API\AddHostTemplate;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplatePresenterInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostTemplateSaasPresenter extends AbstractPresenter implements AddHostTemplatePresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(AddHostTemplateResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {

            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'name' => $response->name,
                        'alias' => $response->alias,
                        'snmp_version' => $response->snmpVersion,
                        'snmp_community' => $this->emptyStringAsNull($response->snmpCommunity),
                        'timezone_id' => $response->timezoneId,
                        'severity_id' => $response->severityId,
                        'check_timeperiod_id' => $response->checkTimeperiodId,
                        'note_url' => $this->emptyStringAsNull($response->noteUrl),
                        'note' => $this->emptyStringAsNull($response->note),
                        'action_url' => $this->emptyStringAsNull($response->actionUrl),
                        'is_locked' => $response->isLocked,
                        'categories' => $response->categories,
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }
    }
}
