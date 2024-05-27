<?php

namespace Tests\Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsPresenterInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsResponse;

class MigrateAllCredentialsPresenterStub implements MigrateAllCredentialsPresenterInterface
{
    public ResponseStatusInterface|MigrateAllCredentialsResponse $response;

    public function presentResponse(ResponseStatusInterface|MigrateAllCredentialsResponse $response): void
    {
        $this->response = $response;
    }
}