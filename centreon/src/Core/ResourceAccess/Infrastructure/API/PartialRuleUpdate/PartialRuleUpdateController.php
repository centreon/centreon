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

namespace Core\ResourceAccess\Infrastructure\API\PartialRuleUpdate;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\ResourceAccess\Application\UseCase\PartialRuleUpdate\PartialRuleUpdate;
use Core\ResourceAccess\Application\UseCase\PartialRuleUpdate\PartialRuleUpdateRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PartialRuleUpdateController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(
        int $ruleId,
        Request $request,
        PartialRuleUpdate $useCase,
        DefaultPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $dto = $this->createDtoFromRequest($ruleId, $request);
            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param int $ruleId
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return PartialRuleUpdateRequest
     */
    private function createDtoFromRequest(int $ruleId, Request $request): PartialRuleUpdateRequest
    {
        /**
         * @var array{
         *      name?: string,
         *      description?: string|null,
         *      is_enabled?: bool
         * }
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialRuleUpdateSchema.json');

        $dto = new PartialRuleUpdateRequest();
        $dto->id = $ruleId;

        if (\array_key_exists('name', $data)) {
            $dto->name = $data['name'];
        }

        if (\array_key_exists('description', $data)) {
            $dto->description = $data['description'];
        }

        if (\array_key_exists('is_enabled', $data)) {
            $dto->isEnabled = $data['is_enabled'];
        }

        return $dto;
    }
}
