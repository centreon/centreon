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

namespace Core\Security\Token\Infrastructure\API\PartialUpdateToken;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\UseCase\PartialUpdateToken\PartialUpdateToken;
use Core\Security\Token\Application\UseCase\PartialUpdateToken\PartialUpdateTokenRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartialUpdateTokenController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param DefaultPresenter $presenter
     * @param PartialUpdateToken $useCase
     * @param string $tokenName
     * @param int $userId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        DefaultPresenter $presenter,
        PartialUpdateToken $useCase,
        string $tokenName,
        int $userId
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /**
             * @var array{
             *     is_revoked?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateTokenSchema.json');
            $requestDto = new PartialUpdateTokenRequest();
            if (array_key_exists('is_revoked', $data)) {
                $requestDto->isRevoked = $data['is_revoked'];
            }

            $useCase($requestDto, $presenter, $tokenName, $userId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new ErrorResponse(TokenException::errorWhilePartiallyUpdatingToken())
            );
        }

        return $presenter->show();
    }
}
