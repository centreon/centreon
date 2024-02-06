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

namespace Core\Dashboard\Infrastructure\API\ShareDashboard;

use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboardPresenterInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;

final class ShareDashboardPresenter extends AbstractPresenter implements ShareDashboardPresenterInterface
{
    public function presentResponse(ResponseStatusInterface $response): void
    {
        $this->setResponseStatus($response);
    }
}
