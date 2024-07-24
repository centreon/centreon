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

namespace Core\AdditionalConnector\Infrastructure\API\FindAdditionalConnectors;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors\FindAdditionalConnectorsPresenterInterface;
use Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors\FindAdditionalConnectorsResponse;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindAdditionalConnectorsPresenter extends AbstractPresenter implements FindAdditionalConnectorsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindAdditionalConnectorsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];
            foreach ($response->additionalConnectors as $acc) {
                $result[] = [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'type' => $acc->type->value,
                    'description' => $acc->description,
                    'created_at' => $this->formatDateToIso8601($acc->createdAt),
                    'created_by' => $acc->createdBy,
                    'updated_at' => $this->formatDateToIso8601($acc->updatedAt),
                    'udpated_by' => $acc->updatedBy,
                ];
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
