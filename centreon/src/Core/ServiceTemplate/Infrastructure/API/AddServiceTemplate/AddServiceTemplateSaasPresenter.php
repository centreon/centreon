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

namespace Core\ServiceTemplate\Infrastructure\API\AddServiceTemplate;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplatePresenterInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplateResponse;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\MacroDto;

class AddServiceTemplateSaasPresenter extends AbstractPresenter implements AddServiceTemplatePresenterInterface
{
    public function __construct(PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|AddServiceTemplateResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'name' => $response->name,
                        'alias' => $response->alias,
                        'service_template_id' => $response->serviceTemplateId,
                        'check_timeperiod_id' => $response->checkTimePeriodId,
                        'note' => $response->note,
                        'note_url' => $response->noteUrl,
                        'action_url' => $response->actionUrl,
                        'severity_id' => $response->severityId,
                        'host_templates' => $response->hostTemplateIds,
                        'is_locked' => $response->isLocked,
                        'categories' => array_map(fn($category): array => [
                            'id' => $category['id'],
                            'name' => $category['name'],
                        ], $response->categories),
                        'macros' => array_map(fn(MacroDto $macro): array => [
                            'name' => $macro->name,
                            // Note: do not handle vault storage at the moment
                            'value' => $macro->isPassword ? null : $macro->value,
                            'is_password' => $macro->isPassword,
                            'description' => $macro->description,
                        ], $response->macros),
                        'groups' => array_map(fn($group): array => [
                            'id' => $group['serviceGroupId'],
                            'name' => $group['serviceGroupName'],
                            'host_template_id' => $group['hostTemplateId'],
                            'host_template_name' => $group['hostTemplateName'],
                        ], $response->groups),
                    ]
                )
            );
        }
    }
}
