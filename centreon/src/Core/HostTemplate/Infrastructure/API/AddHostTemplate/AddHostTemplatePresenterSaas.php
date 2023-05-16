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
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplatePresenterInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostTemplatePresenterSaas extends AbstractPresenter implements AddHostTemplatePresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(AddHostTemplateResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'id' => $response->id,
                'name' => $response->name,
                'alias' => $response->alias,
                'snmpVersion' => $response->snmpVersion,
                'snmpCommunity' => $this->emptyStringAsNull($response->snmpCommunity),
                'timezoneId' => $response->timezoneId,
                'severityId' => $response->severityId,
                'checkTimeperiodId' => $response->checkTimeperiodId,
                'noteUrl' => $this->emptyStringAsNull($response->noteUrl),
                'note' => $this->emptyStringAsNull($response->note),
                'actionUrl' => $this->emptyStringAsNull($response->actionUrl),
                'isLocked' => $response->isLocked,
            ]);

            // NOT setting location as required route does not currently exist
        }
    }
}
