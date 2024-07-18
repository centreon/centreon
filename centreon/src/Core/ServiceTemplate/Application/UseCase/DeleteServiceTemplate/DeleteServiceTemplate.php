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

namespace Core\ServiceTemplate\Application\UseCase\DeleteServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;

final class DeleteServiceTemplate
{
    use LoggerTrait,VaultTrait;

    /**
     * @param ReadServiceTemplateRepositoryInterface $readRepository
     * @param WriteServiceTemplateRepositoryInterface $writeRepository
     * @param ContactInterface $user
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param ReadServiceMacroRepositoryInterface $readServiceMacroRepository
     */
    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readRepository,
        private readonly WriteServiceTemplateRepositoryInterface $writeRepository,
        private readonly ContactInterface $user,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
    ) {
         $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
    }

    /**
     * @param int $serviceTemplateId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $serviceTemplateId, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to delete a service template",
                    ['user_id' => $this->user->getId(), 'service_template_id' => $serviceTemplateId]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceTemplateException::deleteNotAllowed())
                );

                return;
            }

            if (($serviceTemplate = $this->readRepository->findById($serviceTemplateId)) === null) {
                $this->error('Service template not found', ['service_template_id' => $serviceTemplateId]);
                $presenter->setResponseStatus(new NotFoundResponse('Service template'));

                return;
            }

            if ($serviceTemplate->isLocked()) {
                $this->error(
                    'The service template is locked and cannot be delete',
                    ['service_template_id' => $serviceTemplateId]
                );
                $presenter->setResponseStatus(
                    new ErrorResponse(
                        ServiceTemplateException::cannotBeDelete($serviceTemplate->getName())->getMessage()
                    )
                );

                return;
            }

            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveServiceUuidFromVault($serviceTemplateId);
                if ($this->uuid !== null) {
                    $this->writeVaultRepository->delete($this->uuid);
                }
            }

            $this->writeRepository->deleteById($serviceTemplateId);
            $presenter->setResponseStatus(new NoContentResponse());
            $this->info(
                'Service template deleted',
                [
                    'service_template_id' => $serviceTemplateId,
                    'user_id' => $this->user->getId(),
                ]
            );
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceTemplateException::errorWhileDeleting($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $serviceTemplateId
     *
     * @throws \Throwable
     */
    private function retrieveServiceUuidFromVault(int $serviceTemplateId): void
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplateId);
        foreach ($macros as $macro) {
            if (
                $macro->isPassword() === true
                && null !== ($this->uuid = $this->getUuidFromPath($macro->getValue()))
            ) {
                break;
            }
        }
    }
}
