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

namespace Core\HostCategory\Infrastructure\API\AddHostCategory;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategoryResponse;
use Core\Infrastructure\Common\Api\Router;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class AddHostCategoryPresenter extends AbstractPresenter
{
    use LoggerTrait;
    private const ROUTE_NAME = 'FindHostCategory';

    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
        readonly private Router $router
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && $data->getPayload() instanceof AddHostCategoryResponse
        ) {
            $payload = $data->getPayload();
            $data->setPayload([
                'id' => $payload->id,
                'name' => $payload->name,
                'alias' => $payload->alias,
                'is_activated' => $payload->isActivated,
                'comment' => $payload->comment,
            ]);

            try {
                $this->setResponseHeaders([
                    'Location' => $this->router->generate(self::ROUTE_NAME, ['id' => $payload->id]),
                ]);
            } catch (\Throwable $ex) {
                $this->error('Impossible to generate the location header', [
                    'message' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                    'route' => self::ROUTE_NAME,
                    'payload' => $payload,
                ]);
            }
        }
        parent::present($data);
    }
}
