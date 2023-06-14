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

namespace Core\ServiceTemplate\Infrastructure\API\PartialUpdateServiceTemplate;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplate;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplateRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type _ServiceTemplate = array{
 *      host_templates: list<int>
 * }
 */
class PartialUpdateServiceTemplateController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param PartialUpdateServiceTemplate $useCase
     * @param DefaultPresenter $presenter
     * @param bool $isCloudPlatform
     * @param int $serviceTemplateId
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(
        PartialUpdateServiceTemplate $useCase,
        DefaultPresenter $presenter,
        bool $isCloudPlatform,
        int $serviceTemplateId,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $validationSchema = $isCloudPlatform
            ? 'PartialUpdateServiceTemplateSaasSchema.json'
            : 'PartialUpdateServiceTemplateOnPremSchema.json';

        try {
            /** @var _ServiceTemplate $data
             */
            $data = $this->validateAndRetrieveDataSent(
                $request,
                __DIR__ . DIRECTORY_SEPARATOR . $validationSchema
            );
            $useCase($this->createDto($serviceTemplateId, $data), $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param int $serviceTemplateId
     * @param _ServiceTemplate $request
     *
     * @return PartialUpdateServiceTemplateRequest
     */
    private function createDto(int $serviceTemplateId, array $request): PartialUpdateServiceTemplateRequest
    {
        $serviceTemplate = new PartialUpdateServiceTemplateRequest($serviceTemplateId);
        if (array_key_exists('host_templates', $request)) {
            $serviceTemplate->hostTemplates = $request['host_templates'];
        }

        return $serviceTemplate;
    }
}
