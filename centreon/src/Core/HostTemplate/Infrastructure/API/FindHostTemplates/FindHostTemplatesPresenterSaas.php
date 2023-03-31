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

namespace Core\HostTemplate\Infrastructure\API\FindHostTemplates;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplatesResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindHostTemplatesPresenterSaas extends AbstractPresenter
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindHostTemplatesResponse $data
     */
    public function present(mixed $data): void
    {
        $result = [];

        foreach ($data->hostTemplates as $hostTemplate) {
            $result[] = [
                'id' => $hostTemplate['id'],
                'name' => $hostTemplate['name'],
                'alias' => $hostTemplate['alias'],
                'snmpVersion' => $hostTemplate['snmpVersion'],
                'snmpCommunity' => $this->emptyStringAsNull($hostTemplate['snmpCommunity']),
                'timezoneId' => $hostTemplate['timezoneId'],
                'severityId' => $hostTemplate['severityId'],
                'checkTimeperiodId' => $hostTemplate['checkTimeperiodId'],
                'noteUrl' => $this->emptyStringAsNull($hostTemplate['noteUrl']),
                'note' => $this->emptyStringAsNull($hostTemplate['note']),
                'actionUrl' => $this->emptyStringAsNull($hostTemplate['actionUrl']),
                'isLocked' => $hostTemplate['isLocked'],
            ];
        }

        parent::present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
