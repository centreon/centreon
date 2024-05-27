<?php

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

    private function convertTypeToString(CredentialTypeEnum $type): string {
        return match ($type) {
            CredentialTypeEnum::TYPE_HOST => 'host',
            CredentialTypeEnum::TYPE_SERVICE => 'service',
            CredentialTypeEnum::TYPE_HOST_TEMPLATE => 'host_template',
            default => throw new \LogicException('Unhandled value')
        };
    }

    private function prefixMacroName(CredentialRecordedDto|CredentialErrorDto $dto): string
    {
        if ($dto->credentialName === "_HOSTSNMPCOMMUNITY") {
            return $dto->credentialName;
        }

        return match ($dto->type) {
            CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE => "_HOST" . $dto->credentialName,
            CredentialTypeEnum::TYPE_SERVICE => "_SERVICE" . $dto->credentialName
        };


    }
}