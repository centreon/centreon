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

namespace Core\Broker\Infrastructure\API\AddBrokerOutput;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\UseCase\AddBrokerOutput\AddBrokerOutput;
use Core\Broker\Application\UseCase\AddBrokerOutput\AddBrokerOutputRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddBrokerOutputController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param int $brokerId
     * @param Request $request
     * @param AddBrokerOutput $useCase
     * @param AddBrokerOutputPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $brokerId,
        Request $request,
        AddBrokerOutput $useCase,
        AddBrokerOutputPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $dto = new AddBrokerOutputRequest();

        try {
            /**
             * @var array{
             *     name: string,
             *     type: int,
             *     parameters: array<string,mixed>
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddBrokerOutputSchema.json');

            $dto->brokerId = $brokerId;
            $dto->name = $data['name'];
            $dto->type = $data['type'];
            $dto->parameters = $data['parameters'];

        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(BrokerException::addBrokerOutput()));
        }

        $useCase($dto, $presenter);

        return $presenter->show();
    }
}
