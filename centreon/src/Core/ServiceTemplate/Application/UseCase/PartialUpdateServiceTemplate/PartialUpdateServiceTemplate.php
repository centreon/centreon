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

namespace Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

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
use Core\Common\Application\Type\NoValue;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;

class PartialUpdateServiceTemplate
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readRepository,
        private readonly WriteServiceTemplateRepositoryInterface $writeRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $storageEngine,
    ) {
    }

    public function __invoke(
        PartialUpdateServiceTemplateRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            $this->info('Update the service template', ['request' => $request]);
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to update a service template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceTemplateException::updateNotAllowed())
                );

                return;
            }

            if (! $this->readRepository->exists($request->id)) {
                $this->error('Service template not found', ['service_template_id' => $request->id]);
                $presenter->setResponseStatus(new NotFoundResponse('Service template'));

                return;
            }

            if (! ($request->hostTemplates instanceof NoValue)) {
                $this->assertHostTemplateIds($request);
                $this->linkServiceTemplateToHostTemplates($request);
            }

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceTemplateException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceTemplateException::errorWhileUpdating()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * Check if all host template ids exist.
     *
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws ServiceTemplateException
     */
    private function assertHostTemplateIds(PartialUpdateServiceTemplateRequest $request): void
    {
        if (is_array($request->hostTemplates)) {
            $hostTemplateIds = array_unique($request->hostTemplates);
            $hostTemplateIdsFound = $this->readHostTemplateRepository->findAllExistingIds($hostTemplateIds);
            if ([] !== ($diff = array_diff($hostTemplateIds, $hostTemplateIdsFound))) {
                throw ServiceTemplateException::idsDoesNotExist('host_templates', $diff);
            }
        }
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToHostTemplates(PartialUpdateServiceTemplateRequest $request): void
    {
        if (is_array($request->hostTemplates)) {
            $this->storageEngine->startTransaction();
            try {
                $this->debug('Start transaction');
                $this->info('Unlink existing host templates from service template', [
                    'service_template_id' => $request->id,
                    'host_templates' => $request->hostTemplates,
                ]);
                $this->writeRepository->unlinkHostTemplates($request->id);
                $this->info('Link host templates to service template', [
                    'service_template_id' => $request->id,
                    'host_templates' => $request->hostTemplates,
                ]);
                $this->writeRepository->linkToHostTemplates($request->id, $request->hostTemplates);
                $this->debug('Commit transaction');
                $this->storageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->debug('Rollback transaction');
                $this->storageEngine->rollbackTransaction();

                throw $ex;
            }
        }
    }
}
