<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceCategory\Infrastructure\API\GetServiceCategory;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\ServiceCategory\Application\UseCase\GetServiceCategory\GetServiceCategoryResponse;

class GetServiceCategoryPresenter extends AbstractPresenter
{
    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        if ($data instanceof GetServiceCategoryResponse) {
            $data = [
                'id' => $data->id,
                'name' => $data->name,
                'alias' => $data->alias,
                'is_activated' => $data->isActivated,
            ];
        }

        parent::present($data);
    }
}
