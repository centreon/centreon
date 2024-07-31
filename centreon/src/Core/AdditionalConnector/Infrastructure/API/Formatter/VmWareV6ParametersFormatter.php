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

namespace Core\AdditionalConnector\Infrastructure\API\Formatter;

use Core\AdditionalConnector\Domain\Model\Type;

/**
 * @phpstan-import-type _VmWareV6 from \Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation\VmWareV6DataValidator
 *
 * @phpstan-type _VmWareV6Formatted array{
 *      port:int,
 *      vcenters:array<array{name:string,url:string}>
 *  }
 */
class VmWareV6ParametersFormatter implements ParametersFormatterInterface
{
    public function isValidFor(Type $type): bool
    {
        return Type::VMWARE_V6 === $type;
    }

    /**
     * @inheritDoc
     *
     * @param _VmWareV6 $parameters
     *
     * @return _VmWareV6Formatted
     */
    public function format(array $parameters): array
    {
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            $parameters['vcenters'][$index]['username'] = null;
            $parameters['vcenters'][$index]['password'] = null;

        }

        /** @var _VmWareV6Formatted $parameters */
        return $parameters;
    }
}
