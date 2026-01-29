<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Tests\Resources\Mock;

use Centreon\Application\DataRepresenter;
use Centreon\Infrastructure\Webservice;

/**
 * Mock of webservice class
 */
class WebserviceMock extends Webservice\WebServiceAbstract implements
    Webservice\WebserviceAutorizeRestApiInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'webservice_mock';
    }

    /**
     * Return empty list
     *
     * @return DataRepresenter\Response
     */
    public function getList(): DataRepresenter\Response
    {
        return new DataRepresenter\Response([]);
    }
}
