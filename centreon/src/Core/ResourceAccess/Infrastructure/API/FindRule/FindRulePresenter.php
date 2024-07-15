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

namespace Core\ResourceAccess\Infrastructure\API\FindRule;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRulePresenterInterface;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRuleResponse;

final class FindRulePresenter extends AbstractPresenter implements FindRulePresenterInterface
{
    /**
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindRuleResponse|ResponseStatusInterface $response
     */
    public function presentResponse(FindRuleResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'id' => $response->id,
                'name' => $response->name,
                'description' => $response->description,
                'is_enabled' => $response->isEnabled,
                'contacts' => [
                    'values' => $response->contacts,
                    'all' => $response->applyToAllContacts,
                ],
                'contact_groups' => [
                    'values' => $response->contactGroups,
                    'all' => $response->applyToAllContactGroups,
                ],
                'dataset_filters' => $response->datasetFilters,
            ]);
        }
    }
}
