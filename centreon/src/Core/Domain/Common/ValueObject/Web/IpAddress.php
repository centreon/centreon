<?php

declare(strict_types=1);

namespace Core\Domain\Common\ValueObject\Web;

use Core\Domain\Common\Exception\InvalidValueObjectException;
use Core\Domain\Common\ValueObject\LiteralString;

/**
 * Class
 *
 * @class   IpAddress
 * @package Core\Domain\Common\ValueObject\Web
 */
final readonly class IpAddress extends LiteralString
{
    /**
     * IpAddress constructor
     *
     * @param string $ip_address
     * @throws InvalidValueObjectException
     */
    public function __construct(string $ip_address)
    {
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            throw new InvalidValueObjectException("{$ip_address} is an invalid IP Address");
        }
        parent::__construct($ip_address);
    }
}
