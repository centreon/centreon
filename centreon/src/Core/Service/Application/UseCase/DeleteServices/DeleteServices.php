<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Application\UseCase\DeleteServices;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;

final class DeleteServices
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param WriteServiceRepositoryInterface $writeServiceRepository
     * @param ReadServiceRepositoryInterface $readServiceRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * @param DeleteServicesRequest $request
     *
     * @return DeleteServicesResponse
     */
    public function __invoke(DeleteServicesRequest $request): DeleteServicesResponse
    {
        return $this->deleteServices($request->serviceIds);
    }

    /**
     * @param int[] $serviceIds
     *
     * @return DeleteServicesResponse
     */
    private function deleteServices(array $serviceIds): DeleteServicesResponse
    {
        $results = [];
        foreach ($serviceIds as $serviceId) {
            $statusResponse = new DeleteServicesStatusResponse();
            $statusResponse->id = $serviceId;
            try {
                if (! $this->serviceExists($serviceId)) {
                    $statusResponse->status = ResponseCodeEnum::NotFound;
                    $statusResponse->message = (new NotFoundResponse('Service'))->getMessage();
                    $results[] = $statusResponse;
                    continue;
                }

                $this->writeServiceRepository->delete($serviceId);
                $results[] = $statusResponse;
            } catch (\Throwable $ex) {
                $this->error($ex->getMessage(), ['trace' =>  (string) $ex]);
                $statusResponse->status = ResponseCodeEnum::Error;
                $statusResponse->message = ServiceException::errorWhileDeleting($ex)->getMessage();

                $results[] = $statusResponse;
            }
        }

        return new DeleteServicesResponse($results);
    }

    /**
     * Check that service exists for the user regarding ACLs
     *
     * @param int $serviceId
     *
     * @return bool
     */
    private function serviceExists(int $serviceId): bool
    {
        return $this->user->isAdmin()
            ? $this->readServiceRepository->exists($serviceId)
            : $this->readServiceRepository->existsByAccessGroups(
                $serviceId,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
    }
}