<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Contact\Domain\AdminResolver;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\TinyRule;

final class FindRules
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadResourceAccessRepositoryInterface $repository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly AdminResolver $adminResolver,
    ) {
    }

    /**
     * @param FindRulesPresenterInterface $presenter
     */
    public function __invoke(FindRulesPresenterInterface $presenter): void
    {
        try {
            $presenter->presentResponse(
                $this->createResponse(
                    $this->adminResolver->isAdmin($this->user)
                        ? $this->repository->findAllByRequestParameters($this->requestParameters)
                        : $this->repository->findAllByRequestParametersAndUserId($this->requestParameters, $this->user->getId())
                )
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
     * @param TinyRule[] $rules
     *
     * @return FindRulesResponse
     */
    private function createResponse(array $rules): FindRulesResponse
    {
        $response = new FindRulesResponse();
        foreach ($rules as $rule) {
            $dto = new TinyRuleDto(
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
