<?php

/*
 * Centreon
 *
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Module\Application\Repository;

use Core\Module\Domain\ModuleInformation;

interface ModuleInformationRepositoryInterface
{
    public function findByName(string $name): ?ModuleInformation;
}

