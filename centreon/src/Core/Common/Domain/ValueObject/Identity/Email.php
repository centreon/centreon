<?php

declare(strict_types=1);

namespace Core\Common\Domain\ValueObject\Identity;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\LiteralString;

/**
 * Class
 *
 * @class   Email
 * @package Core\Common\Domain\ValueObject\Identity
 */
final readonly class Email extends LiteralString
{
    /**
     * @param string $value
     *
     * @throws ValueObjectException
     */
    public function __construct(string $value)
    {
        if (empty($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValueObjectException(
                "Invalid email",
                [
                    'value' => $value,
                ]
            );
        }

        parent::__construct($value);
    }

    /**
     * Returns the local part of the email address.
     *
     * @return LiteralString
     */
    public function getLocalPart(): LiteralString
    {
        $parts = explode('@', $this->value);

        return new LiteralString($parts[0]);
    }

    /**
     * Returns the domain part of the email address.
     *
     * @return LiteralString
     */
    public function getDomainPart(): LiteralString
    {
        $parts = explode('@', $this->value);

        return new LiteralString($parts[1]);
    }
}
