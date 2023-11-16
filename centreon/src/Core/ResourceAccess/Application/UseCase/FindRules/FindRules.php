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

namespace Core\ResourceAccess\Application\UseCase\FindRules;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadRuleRepositoryInterface;
use Core\ResourceAccess\Domain\Model\Rule;

final class FindRules
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param ReadRuleRepositoryInterface $repository
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadRuleRepositoryInterface $repository,
        private readonly RequestParametersInterface $requestParameters,
    ) {
    }

    /**
     * @param FindRulesPresenterInterface $presenter
     */
    public function __invoke(FindRulesPresenterInterface $presenter): void {
        $this->info('Finding resource access rules', ['request_parameters' => $this->requestParameters]);

        if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW)) {
            $this->error(
                "User doesn't have sufficient rights to list resource access rules",
                [
                    'user_id' => $this->user->getId(),
                ]
            );
            $presenter->presentResponse(
                new ForbiddenResponse(RuleException::notAllowed()->getMessage())
            );

            return;
        }

        try {
            $rules = $this->repository->findAllByRequestParameters($this->requestParameters);
            $presenter->presentResponse(
                $this->createResponse($rules)
            );
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(RuleException::errorWhileSearchingRules()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Rule[] $rules
     *
     * @return FindRulesResponse
     */
    private function createResponse(array $rules): FindRulesResponse 
    {
        $response = new FindRulesResponse();
        foreach ($rules as $rule) {
            $dto = new RuleDto(
                $rule->getId(),
                $rule->getName(),
                $rule->isEnabled()
            );

            $dto->description = $rule->getDescription();
            $response->rulesDto[] = $dto;
        }

        return $response;
    }
}

