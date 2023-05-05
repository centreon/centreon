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

namespace Core\HostGroup\Infrastructure\API\AddHostGroup;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupResponse;
use Core\Infrastructure\Common\Api\Router;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostGroupPresenterOnPrem extends AbstractPresenter
{
    use PresenterTrait;
    use LoggerTrait;
    private const ROUTE_NAME = 'FindHostGroup';

    /**
     * @param PresenterFormatterInterface $presenterFormatter
     * @param Router $router
     */
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
        readonly private Router $router
    ) {
        $this->presenterFormatter = $presenterFormatter;
        parent::__construct($presenterFormatter);
    }

    /**
     * {@inheritDoc}
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && ($payload = $data->getPayload()) instanceof AddHostGroupResponse
        ) {
            $this->presentCreatedPayload($data, $payload);
        }

        parent::present($data);
    }

    /**
     * @param CreatedResponse<AddHostGroupResponse> $createdResponse
     * @param AddHostGroupResponse $addHostGroupResponse
     */
    private function presentCreatedPayload(
        CreatedResponse $createdResponse,
        AddHostGroupResponse $addHostGroupResponse
    ): void {
        $createdResponse->setPayload(
            [
                'id' => $addHostGroupResponse->id,
                'name' => $addHostGroupResponse->name,
                'alias' => $this->emptyStringAsNull($addHostGroupResponse->alias),
                'notes' => $this->emptyStringAsNull($addHostGroupResponse->notes),
                'notes_url' => $this->emptyStringAsNull($addHostGroupResponse->notesUrl),
                'action_url' => $this->emptyStringAsNull($addHostGroupResponse->actionUrl),
                'icon_id' => $addHostGroupResponse->iconId,
                'icon_map_id' => $addHostGroupResponse->iconMapId,
                'rrd' => $addHostGroupResponse->rrdRetention,
                'geo_coords' => $addHostGroupResponse->geoCoords,
                'comment' => $this->emptyStringAsNull($addHostGroupResponse->comment),
                'is_activated' => $addHostGroupResponse->isActivated,
            ]
        );

        try {
            $this->setResponseHeaders([
                'Location' => $this->router->generate(self::ROUTE_NAME, ['id' => $addHostGroupResponse->id]),
            ]);
        } catch (\Throwable $ex) {
            $this->error('Impossible to generate the location header', [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'route' => self::ROUTE_NAME,
                'payload' => $addHostGroupResponse,
            ]);
        }
    }
}
