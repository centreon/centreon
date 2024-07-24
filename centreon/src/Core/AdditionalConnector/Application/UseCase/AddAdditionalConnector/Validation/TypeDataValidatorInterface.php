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

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorRequest;
use Core\AdditionalConnector\Domain\Model\Type;

interface TypeDataValidatorInterface
{
    /**
     * @param Type $type
     *
     * @return bool
     */
    public function isValidFor(Type $type): bool;

    /**
     * @param AddAdditionalConnectorRequest $request
     *
     * @throws AdditionalConnectorException|AssertionException
     */
    public function validateParametersOrFail(AddAdditionalConnectorRequest $request): void;
}
