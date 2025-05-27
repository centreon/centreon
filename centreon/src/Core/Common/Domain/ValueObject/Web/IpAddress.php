<?php

declare(strict_types=1);

namespace Core\Common\Domain\ValueObject\Web;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\LiteralString;

/**
 * Class
 *
 * @class   IpAddress
 * @package Core\Common\Domain\ValueObject\Web
 */
final readonly class IpAddress extends LiteralString
{
    /**
     * IpAddress constructor
     *
     * @param string $ip_address
     *
     * @throws ValueObjectException
     */
    public function __construct(string $ip_address)
    {
        if (! filter_var($ip_address, FILTER_VALIDATE_IP)) {
            throw new ValueObjectException("{$ip_address} is an invalid IP Address");
        }
        parent::__construct($ip_address);
    }
}
