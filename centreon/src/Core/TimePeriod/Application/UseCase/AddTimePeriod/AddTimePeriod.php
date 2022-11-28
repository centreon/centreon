<?php

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;

class AddTimePeriod
{
    public function __invoke(AddTimePeriodRequest $request, PresenterInterface $presenter)
    {
        try {

        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse('Error'));
        }
    }
}
