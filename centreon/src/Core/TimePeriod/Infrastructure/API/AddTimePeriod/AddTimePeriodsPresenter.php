<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\TimePeriod\Infrastructure\API\AddTimePeriod;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class AddTimePeriodsPresenter extends AbstractPresenter implements PresenterInterface
{
    public function __construct(PresenterFormatterInterface $presenterFormatter)
    {
        $this->presenterFormatter = $presenterFormatter;
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        $response = $data;
        if (is_object($data) && is_a($data, CreatedResponse::class) && $data->getPayload() !== []) {
            /**
             * @var array{
             *     id: int,
             *     name: string,
             *     alias: string,
             *     days: array<array{day: int, time_range: string}>,
             *     templates: array<array{id: int, alias: string}>,
             *     exceptions: array<array{id: int, day_range: string, time_range: string}>
             * } $payload
             */
            $payload = $data->getPayload();
            $response = [
                'id' => $payload['id'],
                'name' => $payload['name'],
                'alias' => $payload['alias'],
                'days' => $payload['days'],
                'templates' => $payload['templates'],
                'exceptions' => $payload['exceptions'],
            ];
        }
        parent::present($response);
    }
}
