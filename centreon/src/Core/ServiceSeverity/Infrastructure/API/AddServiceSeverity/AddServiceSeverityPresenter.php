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

namespace Core\ServiceSeverity\Infrastructure\API\AddServiceSeverity;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverityResponse;
use Core\Infrastructure\Common\Api\Router;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class AddServiceSeverityPresenter extends AbstractPresenter
{
    use LoggerTrait;

    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && $data->getPayload() instanceof AddServiceSeverityResponse
        ) {
            $payload = $data->getPayload();
            $data->setPayload([
                'id' => $payload->id,
                'name' => $payload->name,
                'alias' => $payload->alias,
                'level' => $payload->level,
                'icon_id' => $payload->iconId,
                'is_activated' => $payload->isActivated,
            ]);

            // NOT setting location as required route does not currently exist
        }
        parent::present($data);
    }
}
