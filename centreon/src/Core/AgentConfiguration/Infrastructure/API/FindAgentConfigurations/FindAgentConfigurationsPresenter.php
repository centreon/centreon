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

namespace Core\AgentConfiguration\Infrastructure\API\FindAgentConfigurations;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations\{
    FindAgentConfigurationsPresenterInterface as PresenterInterface,
    FindAgentConfigurationsResponse
};
use Core\Application\Common\UseCase\{
    AbstractPresenter,
    ResponseStatusInterface
};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

final class FindAgentConfigurationsPresenter extends AbstractPresenter implements PresenterInterface
{
    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindAgentConfigurationsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->setResponseStatus($data);
        } else {
            $result = [];
            foreach ($data->agentConfigurations as $agentConfiguration) {
                $result[] = [
                    'id' => $agentConfiguration->id,
                    'name' => $agentConfiguration->name,
                    'type' => $agentConfiguration->type->value,
                    'pollers' => array_map(
                        fn($poller) => ['id' => $poller->id, 'name' => $poller->name],
                        $agentConfiguration->pollers
                    ),
                ];
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
