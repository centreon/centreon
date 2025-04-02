<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Infrastructure\API\ExportResources;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Infrastructure\ExceptionHandler;
use Core\Resources\Application\UseCase\ExportResources\ExportResources;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesRequest;
use Core\Resources\Infrastructure\API\FindResources\FindResourcesRequestValidator as RequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * @phpstan-import-type _RequestParameters from RequestValidator
 */
final class ExportResourcesController extends AbstractController
{
    private const EXPORT_VIEW_TYPE = 'all';

    /**
     * ExportResourcesController constructor
     *
     * @param ContactInterface $contact
     * @param RequestValidator $validator
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly RequestValidator $validator,
        private readonly ExceptionHandler $exceptionHandler,
    ) {}

    /**
     * @param ExportResources $useCase
     * @param ExportResourcesPresenterCsv $presenter
     * @param Request $request
     * @param ExportResourcesInput $input
     *
     * @return Response
     */
    public function __invoke(
        ExportResources $useCase,
        ExportResourcesPresenterCsv $presenter,
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ExportResourcesInput $input
    ): Response {
        try {
            $useCaseRequest = $this->createExportRequest($request, $input);
        } catch (TransformerException $e) {
            $this->exceptionHandler->log($e);
            $presenter->setResponseStatus(new ErrorResponse('Error while creating export request'));

            return $presenter->show();
        }

        $useCase($useCaseRequest, $presenter);

        // if a response status is set before iterating resources to export them, return it (in case of error)
        if ($presenter->getResponseStatus() !== null) {
            return $presenter->show();
        }

        if ($presenter->getViewModel()->getExportedFormat() === 'csv') {
            return $this->createCsvResponse($presenter->getViewModel());
        }

        $presenter->setResponseStatus(new ErrorResponse('Export format not supported'));

        return $presenter->show();
    }

    // ---------------------------------- PRIVATE METHODS ---------------------------------- //

    /**
     * @param Request $request
     * @param ExportResourcesInput $input
     *
     * @throws TransformerException
     * @return ExportResourcesRequest
     */
    private function createExportRequest(Request $request, ExportResourcesInput $input): ExportResourcesRequest
    {
        $filter = $this->validator->validateAndRetrieveRequestParameters($request->query->all(), true);

        $resourceFilter = $this->createResourceFilter($filter);

        return ExportResourcesRequestTransformer::transform(
            input: $input,
            resourceFilter: $resourceFilter,
            contact: $this->contact,
        );
    }

    /**
     * @param _RequestParameters $filter
     *
     * @return ResourceFilter
     */
    private function createResourceFilter(array $filter): ResourceFilter
    {
        return (new ResourceFilter())
            ->setTypes($filter[RequestValidator::PARAM_RESOURCE_TYPE])
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
            ->setRuleId($filter[RequestValidator::PARAM_OPEN_TICKET_RULE_ID]);
    }

    /**
     * @param ExportResourcesViewModel $viewModel
     *
     * @return Response
     */
    private function createCsvResponse(ExportResourcesViewModel $viewModel): Response
    {
        $csvHeaders = $viewModel->getHeaders();
        $resources = $viewModel->getResources();
        /* create a streamed response to avoid memory issues with large data. If an error occurs during the export,
        the error message will be displayed in the csv file and logged (streamed response) */
        $response = new StreamedResponse(function () use ($csvHeaders, $resources): void {
            $csvEncoder = new CsvEncoder();
            try {
                echo $csvEncoder->encode($csvHeaders->values(), CsvEncoder::FORMAT, [
                    CsvEncoder::NO_HEADERS_KEY => true,
                    CsvEncoder::DELIMITER_KEY => ';',
                ]);
                foreach ($resources as $resource) {
                    echo $csvEncoder->encode($resource, CsvEncoder::FORMAT, [
                        CsvEncoder::NO_HEADERS_KEY => true,
                        CsvEncoder::DELIMITER_KEY => ';',
                    ]);
                }
            } catch (\Throwable $throwable) {
                $this->exceptionHandler->log($throwable);
                echo $csvEncoder->encode("Oops ! An error occurred: {$throwable->getMessage()}", CsvEncoder::FORMAT);
            }
        });

        // modify headers to download a csv file
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->getCsvFileName() . '"');

        return $response;
    }

    /**
     * @param string $exportView
     *
     * @return string
     */
    private function getCsvFileName(string $exportView = self::EXPORT_VIEW_TYPE,): string {
        $dateNormalized = str_replace([' ', ':', ',', '/'], '-', $this->getDateFormatted());

        return "ResourceStatusExport_{$exportView}_{$dateNormalized}.csv";
    }

    /**
     * @return string
     */
    private function getDateFormatted(): string
    {
        return (new \DateTime('now'))->format($this->contact->getFormatDate());
    }
}
