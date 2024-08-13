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

namespace Core\AdditionalConnectorConfiguration\Infrastructure\API\UpdateAcc;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAcc;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAccRequest;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateAccController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param int $id
     * @param Request $request
     * @param UpdateAcc $useCase
     * @param DefaultPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $id,
        Request $request,
        UpdateAcc $useCase,
        DefaultPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $addAccRequest = $this->createRequest($id, $request);
            $useCase($addAccRequest, $presenter);
        } catch (InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param int $id
     * @param Request $request
     *
     * @throws InvalidArgumentException
     *
     * @return UpdateAccRequest
     */
    private function createRequest(int $id, Request $request): UpdateAccRequest
    {
        /** @var array{
         *     name:string,
         *     type:string,
         *     description:?string,
         *     pollers:int[],
         *     parameters:array<string,mixed>
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateAccSchema.json');

        $schemaFile = match ($data['type']) {
            'vmware_v6' => 'VmWareV6Schema.json',
            default => throw new InvalidArgumentException(sprintf("Unknow parameter type with value '%s'", $data['type']))
        };

        $this->validateDataSent($request, __DIR__ . "/../Schema/{$schemaFile}");

        $accRequest = new UpdateAccRequest();
        $accRequest->id = $id;
        $accRequest->type = $data['type'];
        $accRequest->name = $data['name'];
        $accRequest->description = $data['description'];
        $accRequest->pollers = $data['pollers'];
        $accRequest->parameters = $data['parameters'];

        return $accRequest;
    }
}
