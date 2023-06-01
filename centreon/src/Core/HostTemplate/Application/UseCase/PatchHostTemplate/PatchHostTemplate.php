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

namespace Core\HostTemplate\Application\UseCase\PatchHostTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\HostMacro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostMacro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostMacroDifference;
use Core\HostTemplate\Domain\Model\HostTemplate;

final class PatchHostTemplate
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param PatchHostTemplateRequest $request
     * @param int $hostTemplateId
     */
    public function __invoke(PatchHostTemplateRequest $request, PresenterInterface $presenter, int $hostTemplateId): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostTemplateException::writeActionsNotAllowed())
                );

                return;
            }

            if (! ($hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId))) {
                $this->error(
                    'Host template not found',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Host template'));

                return;
            }

            $this->updateProperties($request, $hostTemplate);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostTemplateException::patchHostTemplate()));
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param PatchHostTemplateRequest $request
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    private function updateProperties(PatchHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $this->updateMacros($request, $hostTemplate);

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Patch Host Template' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PatchHostTemplateRequest $request
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    private function updateMacros(PatchHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        $this->info(
            'Patch Host Template: update macros',
            ['host_template' => $hostTemplate, 'macros' => $request->macros]
        );

        if ($request->macros === null) {
            $this->info('Macros not provided, nothing to update');

            return;
        }

        /**
         * @var array<string,HostMacro> $directMacros
         * @var array<string,HostMacro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findOriginalMacros($hostTemplate);

        $macros = [];
        foreach ($request->macros as $data) {
            $macroName = strtoupper($data['name']);
            // Note: do not handle vault at the moment
            $macroValue = $data['value'] ?? '';
            if ($data['is_password'] && $data['value'] === null) {
                // retrieve actual password value
                $macroValue = isset($directMacros[$macroName])
                    ? $directMacros[$macroName]->getValue()
                    : (isset($inheritedMacros[$macroName]) ? $inheritedMacros[$macroName]->getValue() : '');
            }

            $macro = new HostMacro(
                $hostTemplate->getId(),
                $data['name'],
                $macroValue,
            );
            $macro->setIsPassword($data['is_password']);
            $macro->setDescription($data['description'] ?? '');

            $macros[$macro->getName()] = $macro;
        }

        $macrosDifference = new HostMacroDifference($directMacros, $inheritedMacros, $commandMacros, $macros);

        foreach ($macrosDifference->getRemoved() as $macro) {
            $this->writeHostMacroRepository->delete($macro);
        }

        $updatedMacros = $macrosDifference->getUpdated();
        $addedMacros = $macrosDifference->getAdded();
        $commonMacros = $macrosDifference->getCommon();
        $order = 0;
        foreach ($macros as $macro) {
            if (isset($updatedMacros[$macro->getName()])) {
                $macro->setOrder($order);
                $this->writeHostMacroRepository->update($macro);
                ++$order;

                continue;
            }
            if (isset($addedMacros[$macro->getName()])) {
                $macro->setOrder($order);
                if ($macro->getDescription() === '') {
                    $macro->setDescription(
                        isset($commandMacros[$macro->getName()])
                        ? $commandMacros[$macro->getName()]->getDescription()
                        : ''
                    );
                }
                $this->writeHostMacroRepository->add($macro);
                ++$order;

                continue;
            }
            if (isset($commonMacros[$macro->getName()])) {
                if (
                    isset($directMacros[$macro->getName()])
                    && $directMacros[$macro->getName()]->getOrder() !== $order
                ) {
                    // macro is the same but its order has changed
                    $macro->setOrder($order);
                    $this->writeHostMacroRepository->update($macro);
                }
                ++$order;
            }
        }
    }

    /**
     * Find macros of a host template:
     * macros linked directly, macros linked through template inheritance, macros linked through command inheritance.
     *
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,HostMacro>,
     *      array<string,HostMacro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findOriginalMacros(HostTemplate $hostTemplate): array
    {
        $inheritanceLineIds = array_merge(
            [$hostTemplate->getId()],
            $this->readHostTemplateRepository->findInheritanceLine($hostTemplate->getId())
        );

        $existingHostMacros = $this->readHostMacroRepository->findByHostIds($inheritanceLineIds);
        /** @var array<string,HostMacro> */
        $inheritedMacros = [];
        /** @var array<string,HostMacro> */
        $directMacros = [];
        foreach ($inheritanceLineIds as $id) {
            foreach ($existingHostMacros as $macro) {
                if ($macro->getHostId() === $hostTemplate->getId()) {
                    $directMacros[$macro->getName()] = $macro;
                } else if (
                    ! isset($inheritedMacros[$macro->getName()])
                    && $macro->getHostId() === $id
                ) {
                    $inheritedMacros[$macro->getName()] = $macro;
                }
            }
        }

        /** @var array<string,CommandMacro> */
        $commandMacros = [];
        if ($hostTemplate->getCheckCommandId() !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $hostTemplate->getCheckCommandId(),
                CommandMacroType::Host
            );

            foreach ($existingCommandMacros as $macro) {
                if (! isset($commandMacros[$macro->getName()])) {
                    $commandMacros[$macro->getName()] = $macro;
                }
            }
        }

        return [$directMacros, $inheritedMacros, $commandMacros];
    }
}
