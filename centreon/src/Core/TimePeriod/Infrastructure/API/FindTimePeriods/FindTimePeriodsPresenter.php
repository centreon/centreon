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

namespace Core\TimePeriod\Infrastructure\API\FindTimePeriods;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\TimePeriod\Application\UseCase\FindTimePeriods\FindTimePeriodsResponse;

class FindTimePeriodsPresenter extends AbstractPresenter implements PresenterInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        readonly private RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * {@inheritDoc}
     *
     * @param FindTimePeriodsResponse $data
     */
    public function present(mixed $data): void
    {
        $response = [
            'result' => [],
        ];
        foreach ($data->timePeriods as $timePeriod) {
            $response['result'][] = [
                'id' => $timePeriod['id'],
                'name' => $timePeriod['name'],
                'alias' => $timePeriod['alias'],
                'days' => $timePeriod['days'],
                'templates' => $timePeriod['templates'],
                'exceptions' => $timePeriod['exceptions'],
                'in_period' => $timePeriod['in_period'],
            ];
        }
        $response['meta'] = $this->requestParameters->toArray();
        parent::present($response);
    }
}
