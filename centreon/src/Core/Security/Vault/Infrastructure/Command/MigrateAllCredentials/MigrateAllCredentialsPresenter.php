<?php

namespace Core\Security\Vault\Infrastructure\Command\MigrateAllCredentials;

use Core\Application\Common\UseCase\CliAbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
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
                        "<ok>    OK  {{$result->credentialName}}</>",
                        "<ok>    - Type: {$result->type}</>",
                        "<ok>    - Resource ID: {$result->resourceId}</>",
                        "<ok>    - Vault Path: {$result->vaultPath}</>",
                        "<ok>    - UUID: {$result->uuid}</>",

                    ]);
                } elseif ($result instanceof CredentialErrorDto) {
                    $this->writeMultiLine([
                        "<error>    ERROR  {{$result->credentialName}}</>",
                        "<error>    - Type: {$result->type}</>",
                        "<error>    - Resource ID: {$result->resourceId}</>",
                        "<error>    - Error: {$result->message}</>",
                    ]);
                }
            }
        }
    }
}