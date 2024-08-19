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

namespace Core\AdditionalConnectorConfiguration\Application\Validation;

use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccRequest;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAccRequest;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6Parameters;

/**
 * @phpstan-import-type _VmWareV6Parameters from VmWareV6Parameters
 */
class VmWareV6DataValidator implements TypeDataValidatorInterface
{
    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return Type::VMWARE_V6 === $type;
    }

    /**
     * @inheritDoc
     */
    public function validateParametersOrFail(AddAccRequest|UpdateAccRequest $request): void
    {
        /** @var _VmWareV6Parameters $parameters */
        $parameters = $request->parameters;

        if ([] === $parameters['vcenters']) {
            throw AccException::arrayCanNotBeEmpty('parameters.vcenters[]');
        }

        $vcenterNames = array_map(fn(array $vcenter) => $vcenter['name'], $parameters['vcenters']);

        if (count(array_unique($vcenterNames)) !== count($vcenterNames)) {
            throw AccException::duplicatesNotAllowed('parameters.vcenters[].name');
        }
    }
}
