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

namespace Core\Security\Token\Infrastructure\API\AddToken;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\UseCase\AddToken\AddToken;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddTokenController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddToken $useCase
     * @param AddTokenPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddToken $useCase,
        AddTokenPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForAPIConfiguration();

         try {
            /** @var array{
             *     name: string,
             *     user_id: int,
             *     expiration_date: string
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddTokenSchema.json');

            $dto = new AddTokenRequest();
            $dto->name = $data['name'];
            $dto->userId = $data['user_id'];
            $dto->expirationDate = new \DateTimeImmutable($data['expiration_date']);

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(TokenException::addToken()));
        }

        return $presenter->show();
    }
}
