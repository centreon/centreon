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

namespace Core\Security\Vault\Infrastructure\Command\MigrateAllCredentials;

use Core\Application\Common\UseCase\CliAbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsPresenterInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsResponse;

class MigrateAllCredentialsPresenter extends CliAbstractPresenter implements MigrateAllCredentialsPresenterInterface
{
    public function presentResponse(MigrateAllCredentialsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->error($response->getMessage());
        } else {
            foreach ($response->results as $result) {
                if ($result instanceof CredentialRecordedDto) {
                    $this->writeMultiLine([
                        "<ok>    OK  {{$this->prefixMacroName($result)}}</>",
                        "<ok>    - Type: {$this->convertTypeToString($result->type)}</>",
                        "<ok>    - Resource ID: {$result->resourceId}</>",
                        "<ok>    - Vault Path: {$result->vaultPath}</>",
                        "<ok>    - UUID: {$result->uuid}</>",
                    ]);
                } elseif ($result instanceof CredentialErrorDto) {
                    $this->writeMultiLine([
                        "<error>    ERROR  {{$this->prefixMacroName($result)}}</>",
                        "<error>    - Type: {$this->convertTypeToString($result->type)}</>",
                        "<error>    - Resource ID: {$result->resourceId}</>",
                        "<error>    - Error: {$result->message}</>",
                    ]);
                }
            }
        }
    }

    /**
     * Convert the enum into a string.
     *
     * @param CredentialTypeEnum $type
     *
     * @return string
     */
    private function convertTypeToString(CredentialTypeEnum $type): string {
        return match ($type) {
            CredentialTypeEnum::TYPE_HOST => 'host',
            CredentialTypeEnum::TYPE_SERVICE => 'service',
            CredentialTypeEnum::TYPE_HOST_TEMPLATE => 'host_template',
            CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD => 'knowledge_base',
            CredentialTypeEnum::TYPE_POLLER_MACRO => 'poller_macro',
            CredentialTypeEnum::TYPE_OPEN_ID => 'open_id',
        };
    }

    /**
     * Prefix the macro with _HOST or _SERVICE depending of the type.
     *
     * @param CredentialRecordedDto|CredentialErrorDto $dto
     *
     * @return string
     */
    private function prefixMacroName(CredentialRecordedDto|CredentialErrorDto $dto): string
    {
        if ($dto->credentialName === '_HOSTSNMPCOMMUNITY') {
            return $dto->credentialName;
        }

        return match ($dto->type) {
            CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE => '_HOST' . $dto->credentialName,
            CredentialTypeEnum::TYPE_SERVICE => '_SERVICE' . $dto->credentialName,
            CredentialTypeEnum::TYPE_POLLER_MACRO,
            CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD,
            CredentialTypeEnum::TYPE_OPEN_ID => $dto->credentialName
        };

    }
}
