<?php

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

enum CredentialTypeEnum
{
    case TYPE_HOST;
    case TYPE_HOST_TEMPLATE;
    case TYPE_SERVICE;
}
