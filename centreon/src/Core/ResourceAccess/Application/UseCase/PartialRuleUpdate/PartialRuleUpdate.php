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

namespace Core\ResourceAccess\Application\UseCase\PartialRuleUpdate;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\Type\NoValue;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRuleValidation;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class PartialRuleUpdate
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadResourceAccessRepositoryInterface $readRepository
     * @param WriteResourceAccessRepositoryInterface $writeRepository
     * @param UpdateRuleValidation $validator
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $readRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeRepository,
        private readonly UpdateRuleValidation $validator,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param PartialRuleUpdateRequest $request
     * @param PresenterInterface $presenter
     */
    public function __invoke(
        PartialRuleUpdateRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            $this->info('Start resource access rule update process');

            if (! $this->isAuthorized()) {
                $this->error(
                    "User doesn't have sufficient rights to create a resource access rule",
                    [
                        'user_id' => $this->user->getId(),
                    ]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(RuleException::notAllowed()->getMessage())
                );

                return;
            }

            $this->debug('Find resource access rule to partially update', ['id' => $request->id]);
            $rule = $this->readRepository->findById($request->id);

            if ($rule === null) {
                $presenter->setResponseStatus(new NotFoundResponse('Resource access rule'));

                return;
            }

            $this->updatePropertiesInTransaction($rule, $request);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (RuleException $exception) {
            $presenter->setResponseStatus(
                match ($exception->getCode()) {
                    RuleException::CODE_CONFLICT => new ConflictResponse($exception),
                    default => new ErrorResponse($exception),
                }
            );
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $exception) {
            $presenter->setResponseStatus(new ErrorResponse(RuleException::addRule()));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @param Rule $rule
     * @param PartialRuleUpdateRequest $request
     *
     * @throws RuleException
     * @throws AssertionFailedException
     * @throws \Exception
     */
    private function updatePropertiesInTransaction(Rule $rule, PartialRuleUpdateRequest $request): void
    {
        $this->info('Partial resource access rule update', ['rule_id' => $rule->getId()]);

        if (! $request->name instanceof NoValue) {
            $this->validator->assertIsValidName($request->name);
            $rule->setName($request->name);
        }

        if (! $request->description instanceof NoValue) {
            $rule->setDescription($request->description ?? '');
        }

        if (! $request->isEnabled instanceof NoValue) {
            $rule->setIsEnabled($request->isEnabled);
        }

        $this->writeRepository->update($rule);
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

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW)
            && $this->isCloudPlatform;
    }
}
