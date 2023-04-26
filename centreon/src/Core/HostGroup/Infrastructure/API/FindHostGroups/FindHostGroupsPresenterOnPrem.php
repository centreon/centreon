<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Infrastructure\API\FindHostGroups;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroupsResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindHostGroupsPresenterOnPrem extends AbstractPresenter
{
    use PresenterTrait;

    public function __construct(
        protected RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * {@inheritDoc}
     *
     * @param FindHostGroupsResponse $data
     */
    public function present(mixed $data): void
    {
        $result = [];
        foreach ($data->hostgroups as $hostgroup) {
            $result[] = [
                'id' => $hostgroup['id'],
                'name' => $hostgroup['name'],
                'alias' => $this->emptyStringAsNull($hostgroup['alias']),
                'notes' => $this->emptyStringAsNull($hostgroup['notes']),
                'notes_url' => $this->emptyStringAsNull($hostgroup['notesUrl']),
                'action_url' => $this->emptyStringAsNull($hostgroup['actionUrl']),
                'icon_id' => $hostgroup['iconId'],
                'icon_map_id' => $hostgroup['iconMapId'],
                'rrd' => $hostgroup['rrdRetention'],
                'geo_coords' => $hostgroup['geoCoords'],
                'comment' => $this->emptyStringAsNull($hostgroup['comment']),
                'is_activated' => $hostgroup['isActivated'],
            ];
        }

        parent::present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
