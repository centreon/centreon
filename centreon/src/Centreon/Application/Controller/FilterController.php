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

namespace Centreon\Application\Controller;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\Filter\Filter;
use Centreon\Domain\Filter\FilterCriteria;
use Centreon\Domain\Filter\FilterException;
use Centreon\Domain\Filter\Interfaces\FilterServiceInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\View\View;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Used to manage filters of the current user
 *
 * @package Centreon\Application\Controller
 */
class FilterController extends AbstractController
{
    public const SERIALIZER_GROUPS_MAIN = ['filter_main'];

    /**
     * PollerController constructor.
     * @param FilterServiceInterface $filterService
     */
    public function __construct(private FilterServiceInterface $filterService)
    {
    }

    /**
     * validate filter data according to json schema
     *
     * @param array<mixed> $filter sent json
     * @return void
     * @throws FilterException
     */
    private function validateFilterSchema(array $filter, string $schemaPath): void
    {
        $filterToValidate = Validator::arrayToObjectRecursive($filter);
        $validator = new Validator();
        $validator->validate(
            $filterToValidate,
            (object) ['$ref' => 'file://' . $schemaPath],
            Constraint::CHECK_MODE_VALIDATE_SCHEMA
        );

        if (!$validator->isValid()) {
            $message = '';
            foreach ($validator->getErrors() as $error) {
                $message .= sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
            throw new FilterException($message);
        }
    }

    /**
     * Entry point to save a filter for a user.
     *
     * @param Request $request
     * @param string $pageName
     * @return View
     * @throws FilterException
     */
    public function addFilter(Request $request, string $pageName): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $payload = $this->validateAndRetrieveDataSent(
            $request,
            $this->getParameter('centreon_path') . 'config/json_validator/latest/Centreon/Filter/AddOrUpdate.json'
        );

        /**
         * @var FilterCriteria[] $filterCriterias
         */
        $filterCriterias = $this->createFilterCriterias($payload);

        $filter = (new Filter())
            ->setPageName($pageName)
            ->setUserId($user->getId())
            ->setName($payload['name'])
            ->setCriterias($filterCriterias);

        $filterId = $this->filterService->addFilter($filter);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        $context = (new Context())->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view($filter)->setContext($context);
    }

    /**
     * @param array<mixed> $data
     * @return FilterCriteria[]
     */
    private function createFilterCriterias(array $data): array
    {
        $filterCriterias = [];
        foreach ($data['criterias'] as $criteria) {
            $filterCriterias[] = (new FilterCriteria())
                ->setName($criteria['name'])
                ->setType($criteria['type'])
                ->setValue($criteria['value'])
                ->setObjectType($criteria['object_type'] ?? null)
                ->setSearchData($criteria['search_data'] ?? null);
        }

        return $filterCriterias;
    }

    /**
     * Entry point to update a filter for a user.
     *
     * @param Request $request
     * @param string $pageName
     * @param int $filterId
     * @return View
     * @throws EntityNotFoundException
     * @throws FilterException
     */
    public function updateFilter(
        Request $request,
        string $pageName,
        int $filterId
    ): View {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $this->filterService->filterByContact($user);

        $payload = $this->validateAndRetrieveDataSent(
            $request,
            $this->getParameter('centreon_path') . 'config/json_validator/latest/Centreon/Filter/AddOrUpdate.json'
        );

        /**
         * @var FilterCriteria[] $filterCriterias
         */
        $filterCriterias = $this->createFilterCriterias($payload);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        if ($filter === null) {
            throw new EntityNotFoundException(
                sprintf(_('Filter id %d not found'), $filterId)
            );
        }

        $filter
            ->setName($payload['name'])
            ->setCriterias($filterCriterias)
            ->setOrder($filter->getOrder());

        $this->filterService->updateFilter($filter);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        $context = (new Context())->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view($filter)->setContext($context);
    }

    /**
     * Entry point to patch a filter for a user.
     *
     * @param Request $request
     * @param string $pageName
     * @param int $filterId
     * @return View
     * @throws EntityNotFoundException
     * @throws FilterException
     */
    public function patchFilter(Request $request, string $pageName, int $filterId): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $this->filterService->filterByContact($user);

        $propertyToPatch = json_decode((string) $request->getContent(), true);
        if (!is_array($propertyToPatch)) {
            throw new FilterException(_('Error when decoding sent data'));
        }

        $this->validateFilterSchema(
            $propertyToPatch,
            $this->getParameter('centreon_path') . 'config/json_validator/latest/Centreon/Filter/Patch.json'
        );

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        if ($filter === null) {
            throw new EntityNotFoundException(
                sprintf(_('Filter id %d not found'), $filterId)
            );
        }

        $filter->setOrder($propertyToPatch['order']);

        $this->filterService->updateFilter($filter);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        $context = (new Context())->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view($filter)->setContext($context);
    }

    /**
     * Entry point to delete a filter for a user.
     *
     * @param string $pageName
     * @param int $filterId
     * @return View
     * @throws EntityNotFoundException
     */
    public function deleteFilter(string $pageName, int $filterId): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        if ($filter === null) {
            throw new EntityNotFoundException(
                sprintf(_('Filter id %d not found'), $filterId)
            );
        }

        $this->filterService->deleteFilter($filter);

        return View::create(null, Response::HTTP_NO_CONTENT, []);
    }

    /**
     * Entry point to get filters saved by the user.
     *
     * @param RequestParametersInterface $requestParameters
     * @param string $pageName
     * @return View
     */
    public function getFilters(RequestParametersInterface $requestParameters, string $pageName): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $filters = $this->filterService->findFiltersByUserId($user->getId(), $pageName);

        $context = (new Context())->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view([
            'result' => $filters,
            'meta' => $requestParameters->toArray(),
        ])->setContext($context);
    }

    /**
     * Entry point to get filter details by id.
     *
     * @param string $pageName
     * @param int $filterId
     * @return View
     * @throws EntityNotFoundException
     */
    public function getFilter(string $pageName, int $filterId): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $this->validatePageNameOrFail($pageName);

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        $this->userHasAccessToResourceStatusOrFail($user);

        $filter = $this->filterService->findFilterByUserId($user->getId(), $pageName, $filterId);
        if ($filter === null) {
            throw new EntityNotFoundException(
                sprintf(_('Filter id %d not found'), $filterId)
            );
        }

        $context = (new Context())->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view($filter)->setContext($context);
    }

    /**
     * @param string $pageName
     *
     * @throws \InvalidArgumentException
     */
    private function validatePageNameOrFail(string $pageName): void
    {
        $pageNames = ['events-view'];
        if (! in_array($pageName, $pageNames, true)) {
            throw new FilterException(
                sprintf(_('Invalid page name. Valid page names are: %s'), implode(', ', $pageNames))
            );
        }
    }

     /**
      * @param Contact $user
      *
      * @throws \RestForbiddenException
      */
     private function userHasAccessToResourceStatusOrFail(Contact $user): void
     {
        if (! $user->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)) {
            throw new FilterException(_('You are not allowed to access the Resources Status page'), 403);
        }
     }
}
