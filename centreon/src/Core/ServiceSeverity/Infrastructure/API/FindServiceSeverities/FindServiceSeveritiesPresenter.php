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

namespace Core\ServiceSeverity\Infrastructure\API\FindServiceSeverities;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceSeverity\Application\UseCase\FindServiceSeverities\FindServiceSeveritiesResponse;

class FindServiceSeveritiesPresenter extends AbstractPresenter
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindServiceSeveritiesResponse $data
     */
    public function present(mixed $data): void
    {
        $result = [];

        foreach ($data->serviceSeverities as $serviceSeverity) {
            $result[] = [
                'id' => $serviceSeverity['id'],
                'name' => $serviceSeverity['name'],
                'alias' => $serviceSeverity['alias'],
                'level' => $serviceSeverity['level'],
                'icon_id' => $serviceSeverity['iconId'],
                'is_activated' => $serviceSeverity['isActivated'],
            ];
        }

        parent::present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
