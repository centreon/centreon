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

namespace Core\ResourceAccess\Application\UseCase\DeleteRule;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class DeleteRule
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ReadResourceAccessRepositoryInterface $readRepository
     * @param WriteResourceAccessRepositoryInterface $writeRepository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     */
    public function __construct(
        private readonly ReadResourceAccessRepositoryInterface $readRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeRepository,
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine
    ) {
    }

    /**
     * @param int $ruleId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $ruleId, PresenterInterface $presenter): void
    {
        /**
         * Check if current user is authorized to perform the action.
         * Only users linked to AUTHORIZED_ACL_GROUPS acl_group and having access in Read/Write rights on the page
         * are authorized to add a Resource Access Rule.
         */
        if (! $this->isAuthorized()) {
            $this->error(
                "User doesn't have sufficient rights to delete a resource access rule",
                [
                    'user_id' => $this->user->getId(),
                ]
            );
            $presenter->setResponseStatus(
                new ForbiddenResponse(RuleException::notAllowed()->getMessage())
            );

            return;
        }

        try {
            $this->info('Starting resource access rule deletion', ['rule_id' => $ruleId]);
            if (! $this->readRepository->exists($ruleId)) {
                $this->error('Resource access rule not found', ['rule_id' => $ruleId]);
                $presenter->setResponseStatus(new NotFoundResponse('Resource access rule'));

                return;
            }

            $this->deleteRule($ruleId);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $exception) {
            $presenter->setResponseStatus(new ErrorResponse(RuleException::errorWhileDeleting($exception)));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @return bool
     */
    private function isAuthorized(): bool
    {
        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        /**
         * User must be
         *     - An admin (belongs to the centreon_admin_acl ACL group)
         *     - authorized to reach the Resource Access Management page.
         */
        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW);
    }

    private function deleteRule(int $ruleId): void
    {
        $this->debug('Starting transaction');
        $this->dataStorageEngine->startTransaction();

        try {
            /**
             * Here are the deletions (on cascade or not) that will occur on rule deletion
             *     - Contact relations (ON DELETE CASCADE)
             *     - Contact Group relations (ON DELETE CASCADE)
             *     - Datasets relations + datasets (NEED MANUAL DELETION)
             *     - DatasetFilters (ON DELETE CASCADE).
             */
            $datasetIds = $this->readRepository->findDatasetIdsByRuleId($ruleId);
            $this->writeRepository->delete($ruleId);
            $this->writeRepository->deleteDatasets($datasetIds);
            $this->debug('Commit transaction');
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $exception) {
            $this->debug('Rollback transaction');
            $this->dataStorageEngine->rollbackTransaction();

            throw $exception;
        }
    }
}
