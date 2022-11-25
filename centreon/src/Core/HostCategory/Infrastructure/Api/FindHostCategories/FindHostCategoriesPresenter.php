<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Infrastructure\Api\FindHostCategories;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesResponse;
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

    public function present(mixed $data): void
    {
        foreach ($data->hostCategories as $hostCategory) {
            $result[]= [
                'id' => $hostCategory['id'],
                'name' => $hostCategory['name'],
                'alias' => $hostCategory['alias'],
                'hosts' => $this->presentHostsForHostCategories($data->hosts[$hostCategory['id']] ?? []),
                'host_templates' => $this->presentHostTemplatesForHostCategories(
                    $data->hostTemplate[$hostCategory['id']] ?? []
                ),
            ];
        }

        parent::present([
            'result' => $result ?? [],
            'meta' => $this->requestParameters->toArray()
        ]);
    }

    /**
     * @param array<array<{id:string,name:string}>> $data
     * @return array<array<{id:string,name:string}>>
     */
    private function presentHostsForHostCategories(array $data): array
    {
        foreach ($data as $host) {
            $result[] = [
                'id' => $host['id'],
                'name' => $host['name']
            ];
        }
        return $result ?? [];
    }

    /**
     * @param array<array<{id:string,name:string}>> $data
     * @return array<array<{id:string,name:string}>>
     */
    private function presentHostTemplatesForHostCategories(array $data): array
    {
        foreach ($data as $hostTemplate) {
            $result[] = [
                'id' => $hostTemplate['id'],
                'name' => $hostTemplate['name']
            ];
        }
        return $result ?? [];
    }
}
