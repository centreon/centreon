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

namespace Core\GraphTemplate\Application\UseCase\FindGraphTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\GraphTemplate\Application\Exception\GraphTemplateException;
use Core\GraphTemplate\Application\Repository\ReadGraphTemplateRepositoryInterface;
use Core\GraphTemplate\Domain\Model\GraphTemplate;

final class FindGraphTemplates
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadGraphTemplateRepositoryInterface $readGraphTemplateRepository,
        private readonly ContactInterface $contact,
    ) {
    }

    /**
     * @param FindGraphTemplatesPresenterInterface $presenter
     */
    public function __invoke(FindGraphTemplatesPresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_GRAPH_TEMPLATES_R)
                && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_GRAPH_TEMPLATES_RW)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see graph templates",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(GraphTemplateException::accessNotAllowed())
                );

                return;
            }

            $graphTemplates = $this->readGraphTemplateRepository->findByRequestParameters($this->requestParameters);

            $presenter->presentResponse($this->createResponse($graphTemplates));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(GraphTemplateException::errorWhileSearching($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param GraphTemplate[] $graphTemplates
     *
     * @return FindGraphTemplatesResponse
     */
    private function createResponse(array $graphTemplates): FindGraphTemplatesResponse
    {
        $response = new FindGraphTemplatesResponse();

        foreach ($graphTemplates as $graphTemplate) {
            $graphTemplateDto = new GraphTemplateDto();
            $graphTemplateDto->id = $graphTemplate->getId();
            $graphTemplateDto->name = $graphTemplate->getName();
            $graphTemplateDto->verticalAxisLabel = $graphTemplate->getVerticalAxisLabel();
            $graphTemplateDto->height = $graphTemplate->getHeight();
            $graphTemplateDto->width = $graphTemplate->getWidth();
            $graphTemplateDto->base = $graphTemplate->getBase();
            $graphTemplateDto->gridLowerLimit = $graphTemplate->getGridLowerLimit();
            $graphTemplateDto->gridUpperLimit = $graphTemplate->getGridUpperLimit();
            $graphTemplateDto->isUpperLimitSizedToMax = $graphTemplate->isUpperLimitSizedToMax();
            $graphTemplateDto->isGraphScaled = $graphTemplate->isGraphScaled();
            $graphTemplateDto->isDefaultCentreonTemplate = $graphTemplate->isDefaultCentreonTemplate();
            $response->graphTemplates[] = $graphTemplateDto;
        }

        return $response;
    }
}
