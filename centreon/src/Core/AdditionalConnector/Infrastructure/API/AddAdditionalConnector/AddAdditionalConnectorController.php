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

namespace Core\AdditionalConnector\Infrastructure\API\AddAdditionalConnector;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnector;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorRequest;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddAdditionalConnectorController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddAdditionalConnector $useCase
     * @param AddAdditionalConnectorPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddAdditionalConnector $useCase,
        AddAdditionalConnectorPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $addAdditionalConnectorRequest = $this->createRequest($request);
            $useCase($addAdditionalConnectorRequest, $presenter);
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
     * @return AddAdditionalConnectorRequest
     */
    private function createRequest(Request $request): AddAdditionalConnectorRequest
    {
        /** @var array{
         *     name:string,
         *     type:string,
         *     description:?string,
         *     pollers:int[],
         *     parameters:array<string,mixed>
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddAdditionalConnectorSchema.json');

        $schemaFile = match ($data['type']) {
            'vmware_v6' => 'VmWareV6Schema.json',
            default => throw new InvalidArgumentException(sprintf("Unknow parameter type with value '%s'", $data['type']))
        };

        $this->validateDataSent($request, __DIR__ . "/../Schema/{$schemaFile}");

        $addAdditionalConnectorRequest = new AddAdditionalConnectorRequest();
        $addAdditionalConnectorRequest->type = $data['type'];
        $addAdditionalConnectorRequest->name = $data['name'];
        $addAdditionalConnectorRequest->description = $data['description'];
        $addAdditionalConnectorRequest->pollers = $data['pollers'];
        $addAdditionalConnectorRequest->parameters = $data['parameters'];

        return $addAdditionalConnectorRequest;
    }
}
