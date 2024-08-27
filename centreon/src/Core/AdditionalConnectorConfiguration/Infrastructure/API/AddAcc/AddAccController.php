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

namespace Core\AdditionalConnectorConfiguration\Infrastructure\API\AddAcc;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAcc;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccRequest;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddAccController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddAcc $useCase
     * @param AddAccPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddAcc $useCase,
        AddAccPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $addAccRequest = $this->createRequest($request);
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
     * @param Request $request
     *
     * @throws InvalidArgumentException
     *
     * @return AddAccRequest
     */
    private function createRequest(Request $request): AddAccRequest
    {
        /** @var array{
         *     name:string,
         *     type:string,
         *     description:?string,
         *     pollers:int[],
         *     parameters:array<string,mixed>
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddAccSchema.json');

        $schemaFile = match ($data['type']) {
            'vmware_v6' => 'VmWareV6Schema.json',
            default => throw new InvalidArgumentException(sprintf("Unknown parameter type with value '%s'", $data['type']))
        };

        $this->validateDataSent($request, __DIR__ . "/../Schema/{$schemaFile}");

        $addAccRequest = new AddAccRequest();
        $addAccRequest->type = $data['type'];
        $addAccRequest->name = $data['name'];
        $addAccRequest->description = $data['description'];
        $addAccRequest->pollers = $data['pollers'];
        $addAccRequest->parameters = $data['parameters'];

        return $addAccRequest;
    }
}
