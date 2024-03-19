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

namespace Core\Migration\Infrastructure\API\FindMigrations;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Migration\Application\UseCase\FindMigrations\FindMigrationsPresenterInterface;
use Core\Migration\Application\UseCase\FindMigrations\FindMigrationsResponse;

class FindMigrationsPresenter extends AbstractPresenter implements FindMigrationsPresenterInterface
{
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindMigrationsResponse|ResponseStatusInterface $response
     */
    public function presentResponse(FindMigrationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $data = [];
            foreach ($response->migrations as $migrationDto) {
                $moduleName = $migrationDto->moduleName ?: 'core';

                if (!array_key_exists($moduleName, $data)) {
                    $data[$moduleName] = [];
                }

                $data[$moduleName][] = [
                    'name' => $migrationDto->name,
                    'description' => $migrationDto->description,
                ];
            }
            $this->present($data);

            /*
            $this->present(
                array_map(static function ($migrationDto) {
                    return [
                        'name' => $migrationDto->name,
                        'module_name' => $migrationDto->moduleName ?: 'core',
                        'description' => $migrationDto->description,
                    ];
                }, $response->migrations),
            );
            */
        }
    }
}
