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

namespace Core\Resources\Infrastructure\API\FindResourcesByParent;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Application\UseCase\FindResourcesByParent\FindResourcesByParent;
use Core\Resources\Infrastructure\API\FindResources\FindResourcesRequestValidator as RequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-import-type _RequestParameters from RequestValidator
 */
final class FindResourcesByParentController extends AbstractController
{
    private const HOST_RESOURCE_TYPE = 'host';

    public function __construct(private readonly RequestValidator $validator)
    {
    }

    /**
     * @param FindResourcesByParent $useCase
     * @param FindResourcesByParentPresenter $presenter
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(
        FindResourcesByParent $useCase,
        FindResourcesByParentPresenter $presenter,
        Request $request
    ): Response
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $filter = $this->validator->validateAndRetrieveRequestParameters($request->query->all());

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
            ->setStates($filter[RequestValidator::PARAM_STATES])
            ->setStatuses($filter[RequestValidator::PARAM_STATUSES])
            ->setStatusTypes($filter[RequestValidator::PARAM_STATUS_TYPES])
            ->setServicegroupNames($filter[RequestValidator::PARAM_SERVICEGROUP_NAMES])
            ->setServiceCategoryNames($filter[RequestValidator::PARAM_SERVICE_CATEGORY_NAMES])
            ->setServiceSeverityNames($filter[RequestValidator::PARAM_SERVICE_SEVERITY_NAMES])
            ->setServiceSeverityLevels($filter[RequestValidator::PARAM_SERVICE_SEVERITY_LEVELS])
            ->setHostgroupNames($filter[RequestValidator::PARAM_HOSTGROUP_NAMES])
            ->setHostCategoryNames($filter[RequestValidator::PARAM_HOST_CATEGORY_NAMES])
            ->setHostSeverityNames($filter[RequestValidator::PARAM_HOST_SEVERITY_NAMES])
            ->setMonitoringServerNames($filter[RequestValidator::PARAM_MONITORING_SERVER_NAMES])
            ->setHostSeverityLevels($filter[RequestValidator::PARAM_HOST_SEVERITY_LEVELS])
            ->setOnlyWithPerformanceData($filter[RequestValidator::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY])
            ->setOnlyWithTicketsOpened($filter[RequestValidator::PARAM_RESOURCES_WITH_OPENED_TICKETS])
            ->setRuleId($filter[RequestValidator::PARAM_OPEN_TICKET_RULE_ID])
            ->setTypes(
                array_filter(
                    $this->validator->getResourceTypes(),
                    $this->removeHostFromTypes(...)
                )
            );
    }

    /**
     * We do not want HOST resource type to be explicitely searched.
     *
     * @param string $resourceType
     *
     * @return bool
     */
    private function removeHostFromTypes(string $resourceType): bool
    {
        return $resourceType !== self::HOST_RESOURCE_TYPE;
    }
}
