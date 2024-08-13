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

namespace Core\AdditionalConnectorConfiguration\Domain\Model;

use Security\Interfaces\EncryptionInterface;

interface AccParametersInterface
{
    /**
     * @param EncryptionInterface $encryption
     * @param AccParametersInterface $currentObj
     * @param array<string,mixed> $newDatas
     *
     * @return AccParametersInterface
     */
    public static function update(
        EncryptionInterface $encryption,
        self $currentObj,
        array $newDatas
    ): self;

    /**
     * @return array<string,mixed>
     */
    public function getEncryptedData(): array;

    /**
     * @return array<string,mixed>
     */
    public function getDecryptedData(): array;

    /**
     * @return array<string,mixed>
     */
    public function getData(): array;

    /**
     * @return array<string,mixed>
     */
    public function getDataWithoutCredentials(): array;
}
