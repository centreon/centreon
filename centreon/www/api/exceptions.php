<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Class
 *
 * @class RestException
 */
class RestException extends Exception
{
    /**
     * RestException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Class
 *
 * @class RestBadRequestException
 */
class RestBadRequestException extends RestException
{
    /** @var int */
    protected $code = 400;

    /**
     * RestBadRequestException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestUnauthorizedException
 */
class RestUnauthorizedException extends RestException
{
    /** @var int */
    protected $code = 401;

    /**
     * RestUnauthorizedException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestForbiddenException
 */
class RestForbiddenException extends RestException
{
    /** @var int */
    protected $code = 403;

    /**
     * RestForbiddenException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestNotFoundException
 */
class RestNotFoundException extends RestException
{
    /** @var int */
    protected $code = 404;

    /**
     * RestNotFoundException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestMethodNotAllowedException
 */
class RestMethodNotAllowedException extends RestException
{
    /** @var int */
    protected $code = 405;

    /**
     * RestMethodNotAllowedException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestConflictException
 */
class RestConflictException extends RestException
{
    /** @var int */
    protected $code = 409;

    /**
     * RestConflictException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestInternalServerErrorException
 */
class RestInternalServerErrorException extends RestException
{
    /** @var int */
    protected $code = 500;

    /**
     * RestInternalServerErrorException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestBadGatewayException
 */
class RestBadGatewayException extends RestException
{
    /** @var int */
    protected $code = 502;

    /**
     * RestBadGatewayException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestServiceUnavailableException
 */
class RestServiceUnavailableException extends RestException
{
    /** @var int */
    protected $code = 503;

    /**
     * RestServiceUnavailableException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestGatewayTimeOutException
 */
class RestGatewayTimeOutException extends RestException
{
    /** @var int */
    protected $code = 504;

    /**
     * RestGatewayTimeOutException constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}

/**
 * Class
 *
 * @class RestPartialContent
 */
class RestPartialContent extends RestException
{
    /** @var int */
    protected $code = 206;

    /**
     * RestPartialContent constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}
