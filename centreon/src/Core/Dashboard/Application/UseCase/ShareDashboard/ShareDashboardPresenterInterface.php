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

namespace Core\Dashboard\Application\UseCase\ShareDashboard;

use Core\Application\Common\UseCase\ResponseStatusInterface;

interface ShareDashboardPresenterInterface
{
    /**
     * @param ResponseStatusInterface $response
     */
    public function presentResponse(ResponseStatusInterface $response): void;
}
