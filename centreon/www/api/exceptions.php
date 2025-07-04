<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
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
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $this->code, $previous);
    }
}
