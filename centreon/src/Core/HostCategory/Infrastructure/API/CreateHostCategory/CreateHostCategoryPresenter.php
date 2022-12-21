<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostCategory\Infrastructure\API\CreateHostCategory;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class CreateHostCategoryPresenter extends AbstractPresenter
{
    /**
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
    }

    /**
     * @param CreateHostCategoryResponse $data
     */
    public function present(mixed $data): void
    {
        parent::present([
            'id' => $data->hostCategory['id'],
            'name' => $data->hostCategory['name'],
            'alias' => $data->hostCategory['alias'],
            'is_activated' => $data->hostCategory['is_activated'],
            'comments' => $data->hostCategory['comment'],
        ]);
    }
}
