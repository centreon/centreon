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

namespace Core\Resources\Infrastructure\API\FindHostsStatusCount;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Application\UseCase\FindHostsStatusCount\FindHostsStatusCount;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FindHostsStatusCountController extends AbstractController
{
    /**
     * @param FindHostsStatusCountRequestValidator $validator
     */
    public function __construct(private readonly FindHostsStatusCountRequestValidator $validator)
    {

    }

    /**
     * @param FindHostsStatusCount $useCase
     * @param FindHostsStatusCountPresenter $presenter
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(
        FindHostsStatusCount $useCase,
        FindHostsStatusCountPresenter $presenter,
        Request $request
    ): Response {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $filter = $this->createResourceFilter($request);
        $useCase($presenter, $filter);

        return $presenter->show();
    }

    /**
     * @param Request $request
     *
     * @return ResourceFilter
     */
    private function createResourceFilter(Request $request): ResourceFilter
    {
        $filter = $this->validator->validateAndRetrieveRequestParameters($request->query->all());

        return (new ResourceFilter())
            ->setHostgroupNames($filter[FindHostsStatusCountRequestValidator::PARAM_HOSTGROUP_NAMES])
            ->setHostCategoryNames($filter[FindHostsStatusCountRequestValidator::PARAM_HOST_CATEGORY_NAMES])
            ->setStatuses($filter[FindHostsStatusCountRequestValidator::PARAM_STATUSES]);
    }
}
