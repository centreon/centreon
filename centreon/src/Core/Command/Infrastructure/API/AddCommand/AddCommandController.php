<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Infrastructure\API\AddCommand;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\UseCase\AddCommand\AddCommand;
use Core\Command\Application\UseCase\AddCommand\AddCommandRequest;
use Core\Command\Application\UseCase\AddCommand\ArgumentDto;
use Core\Command\Application\UseCase\AddCommand\MacroDto;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @phpstan-type _CommandTemplate = array{
 *     name: string,
 *     type: integer,
 *     command_line: string,
 *     is_shell?: bool,
 *     argument_example?: null|string,
 *     arguments?: array<array{name:string,description:null|string}>,
 *     macros?: array<array{name:string,type:int,description:null|string}>,
 *     connector_id?: null|integer,
 *     graph_template_id?: null|integer,
 * }
 */
final class AddCommandController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param AddCommand $useCase
     * @param AddCommandPresenter $presenter
     * @param Request $request
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        AddCommand $useCase,
        AddCommandPresenter $presenter,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var _CommandTemplate $data */
            $data = $this->validateAndRetrieveDataSent(
                $request,
                __DIR__ . '/AddCommandSchema.json'
            );
            $useCase($this->createDto($data), $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param _CommandTemplate $request
     *
     * @return AddCommandRequest
     */
    private function createDto(array $request): AddCommandRequest
    {
        $dto = new AddCommandRequest();
        $dto->name = $request['name'];
        $dto->type = CommandTypeConverter::fromInt($request['type']);
        $dto->commandLine = $request['command_line'];
        $dto->isShellEnabled = $request['is_shell'] ?? false;
        $dto->argumentExample = $request['argument_example'] ?? '';
        $dto->connectorId = $request['connector_id'] ?? null;
        $dto->graphTemplateId = $request['graph_template_id'] ?? null;
        foreach ($request['macros'] ?? [] as $macro) {
            $dto->macros[] = new MacroDto(
                $macro['name'],
                CommandMacroType::from((string) $macro['type']),
                $macro['description'] ?? ''
            );
        }
        foreach ($request['arguments'] ?? [] as $argument) {
            $dto->arguments[] = new ArgumentDto(
                $argument['name'],
                $argument['description'] ?? ''
            );
        }

        return $dto;
    }
}
