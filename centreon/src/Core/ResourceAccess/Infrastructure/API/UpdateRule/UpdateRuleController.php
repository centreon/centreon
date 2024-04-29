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

namespace Core\ResourceAccess\Infrastructure\API\UpdateRule;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRule;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRuleRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type _UpdateRequestData array{
 *     name: string,
 *     description: ?string,
 *     is_enabled: bool,
 *     contacts: array{
 *      ids: list<int>,
 *      all: bool
 *     },
 *     contact_groups: array{
 *      ids: list<int>,
 *      all: bool
 *     },
 *     dataset_filters: non-empty-list<array{
 *      type:string,
 *      resources: list<int>,
 *      ...
 *     }>
 * }
 */
final class UpdateRuleController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(
        int $ruleId,
        Request $request,
        UpdateRule $useCase,
        UpdateRulePresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /** @var _UpdateRequestData $data */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateRuleSchema.json');
        $useCase($this->createDtoFromData($ruleId, $data), $presenter);

        return $presenter->show();
    }

    /**
     * @param int $ruleId
     * @param _UpdateRequestData $data
     *
     * @return UpdateRuleRequest
     */
    private function createDtoFromData(int $ruleId, array $data): UpdateRuleRequest
    {
        $dto = new UpdateRuleRequest();
        $dto->id = $ruleId;
        $dto->name = $data['name'];
        $dto->description = $data['description'] ?? '';
        $dto->isEnabled = $data['is_enabled'];
        $dto->contactIds = $data['contacts']['ids'];
        $dto->contactGroupIds = $data['contact_groups']['ids'];
        $dto->applyToAllContacts = $data['contacts']['all'];
        $dto->applyToAllContactGroups = $data['contact_groups']['all'];
        $dto->datasetFilters = $data['dataset_filters'];

        return $dto;
    }
}
