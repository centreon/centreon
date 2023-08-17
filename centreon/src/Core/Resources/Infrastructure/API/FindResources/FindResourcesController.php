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

namespace Core\Resources\Infrastructure\API\FindResources;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Application\UseCase\FindResources\FindResources;
use Core\Resources\Infrastructure\API\FindResources\FindResourcesRequestValidator as RequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @phpstan-import-type _RequestParameters from RequestValidator
 */
final class FindResourcesController extends AbstractController
{
    public function __construct(private readonly RequestValidator $validator)
    {
    }

    /**
     * @param FindResources $useCase
     * @param FindResourcesPresenter $presenter
     * @param Request $request
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        FindResources $useCase,
        FindResourcesPresenter $presenter,
        Request $request
    ): Response {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $filter = $this->validator->validateAndRetrieveRequestParameters($request);

        $useCase($presenter, $this->createResourceFilter($filter));

        return $presenter->show();
    }

    /**
     * @param _RequestParameters $filter
     *
     * @return ResourceFilter
     */
    private function createResourceFilter(array $filter): ResourceFilter
    {
        return (new ResourceFilter())
            ->setTypes($filter[RequestValidator::RESOURCE_TYPE_PARAM_FILTER])
            ->setStates($filter[RequestValidator::STATES_PARAM_FILTER])
            ->setStatuses($filter[RequestValidator::STATUSES_PARAM_FILTER])
            ->setStatusTypes($filter[RequestValidator::STATUS_TYPES_PARAM_FILTER])
            ->setServicegroupNames($filter[RequestValidator::SERVICEGROUP_NAMES_PARAM_FILTER])
            ->setServiceCategoryNames($filter[RequestValidator::SERVICE_CATEGORY_NAMES_PARAM_FILTER])
            ->setServiceSeverityNames($filter[RequestValidator::SERVICE_SEVERITY_NAMES_PARAM_FILTER])
            ->setServiceSeverityLevels($filter[RequestValidator::SERVICE_SEVERITY_LEVELS_PARAM_FILTER])
            ->setHostgroupNames($filter[RequestValidator::HOST_CATEGORY_NAMES_PARAM_FILTER])
            ->setHostCategoryNames($filter[RequestValidator::HOST_CATEGORY_NAMES_PARAM_FILTER])
            ->setHostSeverityNames($filter[RequestValidator::HOST_CATEGORY_NAMES_PARAM_FILTER])
            ->setMonitoringServerNames($filter[RequestValidator::MONITORING_SERVER_NAMES_PARAM_FILTER])
            ->setHostSeverityLevels($filter[RequestValidator::HOST_SEVERITY_LEVELS_PARAM_FILTER])
            ->setOnlyWithPerformanceData($filter[RequestValidator::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY]);
    }
}
