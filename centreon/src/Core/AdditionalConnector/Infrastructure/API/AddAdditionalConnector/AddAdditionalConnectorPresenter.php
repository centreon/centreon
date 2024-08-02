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

namespace Core\AdditionalConnector\Infrastructure\API\AddAdditionalConnector;

use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorPresenterInterface;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorResponse;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\AdditionalConnector\Infrastructure\API\Formatter\ParametersFormatterInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddAdditionalConnectorPresenter extends AbstractPresenter implements AddAdditionalConnectorPresenterInterface
{
    use PresenterTrait;

    /** @var ParametersFormatterInterface[] */
    private array $parametersFormatters;

    /**
     * @param PresenterFormatterInterface $presenterFormatter
     * @param \Traversable<ParametersFormatterInterface> $parametersFormatters
     */
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
        \Traversable $parametersFormatters
    ) {
        $this->parametersFormatters = iterator_to_array($parametersFormatters);

        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(AddAdditionalConnectorResponse|ResponseStatusInterface $response): void
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
                        'type' => $response->type->value,
                        'description' => $response->description,
                        'parameters' => $this->formatParameters($response->type, $response->parameters),
                        'pollers' => array_map(fn(Poller $poller) => ['id' => $poller->id, 'name' => $poller->name], $response->pollers),
                        'created_by' => $response->createdBy,
                        'created_at' => $this->formatDateToIso8601($response->createdAt),
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }
    }

    /**
     * @param Type $type
     * @param array<string,mixed> $parameters
     *
     * @return array<string,mixed>
     */
    private function formatParameters(Type $type, array $parameters): array
    {
        foreach ($this->parametersFormatters as $formatter) {
            if ($formatter->isValidFor($type)) {
                return $formatter->format($parameters);
            }
        }

        return $parameters;
    }
}
