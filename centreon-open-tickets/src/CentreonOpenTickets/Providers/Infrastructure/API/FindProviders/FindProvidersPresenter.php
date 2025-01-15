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

namespace CentreonOpenTickets\Providers\Infrastructure\API\FindProviders;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use CentreonOpenTickets\Providers\Application\UseCase\FindProvidersPresenterInterface;
use CentreonOpenTickets\Providers\Application\UseCase\FindProvidersResponse;
use CentreonOpenTickets\Providers\Application\UseCase\ProviderDto;
use CentreonOpenTickets\Providers\Domain\Model\ProviderType;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

final class FindProvidersPresenter extends AbstractPresenter implements FindProvidersPresenterInterface
{
    /**
     * @param PresenterFormatterInterface $presenterFormatter
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
        private readonly RequestParametersInterface $requestParameters
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindProvidersResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = array_map(
                fn (ProviderDto $provider): array => [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'type' => $this->normalizeProviderType($provider->type),
                    'is_activated' => $provider->isActivated,
                ],
                $response->providers
            );

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }

    /**
     * @param ProviderType $type
     *
     * @return string
     */
    private function normalizeProviderType(ProviderType $type): string
    {
        return match ($type) {
            ProviderType::Mail => 'Mail',
            ProviderType::Glpi => 'Glpi',
            ProviderType::Otrs => 'Otrs',
            ProviderType::Simple => 'Simple',
            ProviderType::BmcItsm => 'BmcItsm',
            ProviderType::Serena => 'Serena',
            ProviderType::BmcFootprints11 => 'BmcFootprints11',
            ProviderType::EasyvistaSoap => 'EasyvistaSoap',
            ProviderType::ServiceNow => 'ServiceNow',
            ProviderType::Jira => 'Jira',
            ProviderType::GlpiRestApi => 'GlpiRestApi',
            ProviderType::RequestTracker2 => 'RequestTracker2',
            ProviderType::Itop => 'Itop',
            ProviderType::EasyVistaRest => 'EasyVistaRest'
        };
    }
}
