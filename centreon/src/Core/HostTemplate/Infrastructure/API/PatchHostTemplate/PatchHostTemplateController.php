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

namespace Core\HostTemplate\Infrastructure\API\PatchHostTemplate;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\UseCase\PatchHostTemplate\PatchHostTemplate;
use Core\HostTemplate\Application\UseCase\PatchHostTemplate\PatchHostTemplateRequest;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PatchHostTemplateController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PatchHostTemplate $useCase
     * @param DefaultPresenter $presenter
     * @param int $hostTemplateId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        PatchHostTemplate $useCase,
        DefaultPresenter $presenter,
        int $hostTemplateId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /**
             * @var array{
             *      macros?:array<array{name:string,value:string|null,is_password:bool,description:string|null}>
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PatchHostTemplateSchema.json');

            $dto = new PatchHostTemplateRequest();
            $dto->macros = $data['macros'] ?? null;

            $useCase($dto, $presenter, $hostTemplateId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(HostTemplateException::patchHostTemplate()));
        }

        return $presenter->show();
    }
}