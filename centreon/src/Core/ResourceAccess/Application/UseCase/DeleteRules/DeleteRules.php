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

namespace Core\ResourceAccess\Application\UseCase\DeleteRules;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\ResponseCode;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class DeleteRules
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ReadResourceAccessRepositoryInterface $readRepository
     * @param WriteResourceAccessRepositoryInterface $writeRepository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ReadResourceAccessRepositoryInterface $readRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeRepository,
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param DeleteRulesRequest $request
     * @param DeleteRulesPresenterInterface $presenter
     */
    public function __invoke(DeleteRulesRequest $request, DeleteRulesPresenterInterface $presenter): void
    {
        try {
            if (! $this->isAuthorized()) {
                $this->error(
                    "User doesn't have sufficient rights to delete a resource access rule",
                    [
                        'user_id' => $this->user->getId(),
                    ]
                );
                $response = new ForbiddenResponse(RuleException::notAllowed()->getMessage());
            } else {
                $this->debug('Starting transaction');
                $this->dataStorageEngine->startTransaction();

                $response = new DeleteRulesResponse();

                foreach ($request->ids as $ruleId) {
                    try {
                        $statusResponse = $this->deleteRule($ruleId);
                        $response->responseStatuses[] = $this->createResponseStatusDto($ruleId, $statusResponse);
                    } catch (\Throwable $exception) {
                        $statusResponse = new ErrorResponse(RuleException::errorWhileDeleting($exception));
                        $response->responseStatuses[] = $this->createResponseStatusDto($ruleId, $statusResponse);
                        $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
                    }
                }

                $this->debug('Commit transaction');
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $response = new ErrorResponse(RuleException::errorWhileDeleting($exception));
        }

        $presenter->presentResponse($response);
    }

    /**
     * Check if current user is authorized to perform the action.
     * Only users linked to AUTHORIZED_ACL_GROUPS acl_group and having access in Read/Write rights on the page
     * are authorized to add a Resource Access Rule.
     *
     * @return bool
     */
    private function isAuthorized(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

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
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW)
            && $this->isCloudPlatform;
    }

    /**
     * @param int $ruleId
     *
     * @throws \Exception
     * @throws \Throwable
     */
    private function deleteRule(int $ruleId): ResponseStatusInterface
    {
        $this->info('Start resource access rule deletion', ['rule_id' => $ruleId]);

        if (! $this->readRepository->exists($ruleId)) {
            $this->error('Resource access rule not found', ['rule_id' => $ruleId]);

            return new NotFoundResponse('Resource access rule');
        }

        $this->writeRepository->deleteRuleAndDatasets($ruleId);

        return new NoContentResponse();
    }

    /**
     * @param int $ruleId
     * @param ResponseStatusInterface $statusResponse
     *
     * @return DeleteRulesStatusResponse
     */
    private function createResponseStatusDto(
        int $ruleId,
        ResponseStatusInterface $statusResponse
    ): DeleteRulesStatusResponse {
        $dto = new DeleteRulesStatusResponse();
        $dto->id = $ruleId;

        if ($statusResponse instanceof NotFoundResponse) {
            $dto->status = ResponseCode::NotFound;
            $dto->message = $statusResponse->getMessage();
        } elseif ($statusResponse instanceof NoContentResponse) {
            $dto->status = ResponseCode::OK;
            $dto->message = null;
        } else {
            $dto->status = ResponseCode::Error;
            $dto->message = $statusResponse->getMessage();
        }

        return $dto;
    }
}
