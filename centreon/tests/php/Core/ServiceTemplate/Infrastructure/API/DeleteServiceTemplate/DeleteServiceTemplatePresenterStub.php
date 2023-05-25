<?php

/*
 * CENTREON
 *
 * Source Copyright 2005-2023 Centreon
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */
declare(strict_types = 1);

namespace Tests\Core\ServiceTemplate\Infrastructure\API\DeleteServiceTemplate;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;

class DeleteServiceTemplatePresenterStub extends AbstractPresenter
{
    public ?ResponseStatusInterface $response;

    public function setResponseStatus(?ResponseStatusInterface $responseStatus): void
    {
        $this->response = $responseStatus;
    }
}
