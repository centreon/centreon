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

namespace Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation;

use Centreon\Domain\Common\Assertion\Assertion;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorRequest;
use Core\AdditionalConnector\Domain\Model\Type;

/** @phpstan-type _VmWareV6 array{
 *      port:int,
 *      vcenters:array<array{name:string,url:string,username:string,password:string}>
 *  }
 */
class VmWareV6DataValidator implements TypeDataValidatorInterface
{
    private const MAX_LENGTH = 255;
    private const TYPE = Type::VMWARE_V6;

    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return self::TYPE === $type;
    }

    /**
     * @inheritDoc
     */
    public function validateParametersOrFail(AddAdditionalConnectorRequest $request): void
    {
        /** @var _VmWareV6 $parameters */
        $parameters = $request->parameters;

        $vcenterNames = array_map(fn(array $vcenter) => $vcenter['name'], $parameters['vcenters']);

        if (count(array_unique($vcenterNames)) !== count($vcenterNames)) {
            throw AdditionalConnectorException::duplicatesNotAllowed('parameters.vcenters[].name');
        }

        Assertion::range($parameters['port'], 0, 65535, 'parameters.port');

        if ([] === $parameters['vcenters']) {
            throw AdditionalConnectorException::arrayCanNotBeEmpty('parameters.vcenters[]');
        }

        foreach ($parameters['vcenters'] as $index => $vcenter) {
            // Validate min length
            Assertion::notEmpty($vcenter['name'], "parameters.vcenters[{$index}].name");
            Assertion::notEmpty($vcenter['username'], "parameters.vcenters[{$index}].username");
            Assertion::notEmpty($vcenter['password'], "parameters.vcenters[{$index}].password");
            Assertion::notEmpty($vcenter['url'], "parameters.vcenters[{$index}].url");

            // Validate max length
            Assertion::maxLength($vcenter['name'], self::MAX_LENGTH, "parameters.vcenters[{$index}].name");
            Assertion::maxLength($vcenter['username'], self::MAX_LENGTH, "parameters.vcenters[{$index}].username");
            Assertion::maxLength($vcenter['password'], self::MAX_LENGTH, "parameters.vcenters[{$index}].password");
            Assertion::maxLength($vcenter['url'], self::MAX_LENGTH, "parameters.vcenters[{$index}].url");

            // Validate specific format
            Assertion::urlOrIpOrDomain($vcenter['url'], "parameters.vcenters[{$index}].url");
        }
    }
}
