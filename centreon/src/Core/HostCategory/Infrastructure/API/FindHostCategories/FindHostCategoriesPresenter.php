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

namespace Core\HostCategory\Infrastructure\API\FindHostCategories;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class FindHostCategoriesPresenter extends AbstractPresenter
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
    }

    /**
     * @param FindHostCategoriesResponse $data
     */
    public function present(mixed $data): void
    {
        foreach ($data->hostCategories as $hostCategory) {
            $result[] = [
                'id' => $hostCategory['id'],
                'name' => $hostCategory['name'],
                'alias' => $hostCategory['alias'],
                'is_activated' => $hostCategory['is_activated'],
                'comments' => $hostCategory['comment'],
            ];
        }

        parent::present([
            'result' => $result ?? [],
            'meta' => $this->requestParameters->toArray()
        ]);
    }
}
