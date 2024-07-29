<?php

declare(strict_types=1);

/**
 * Class CentreonDbException
 *
 * @class CentreonDbException
 */
class CentreonDbException extends ExceptionAbstract
{

    /**
     * @param string $message
     * @param array $options
     * @param Throwable|null $previous
     */
    public function __construct(string $message, array $options = [], ?Throwable $previous = null)
    {
        parent::__construct($message, self::DATABASE_ERROR_CODE, $options, $previous);
    }

}
