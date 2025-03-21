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
     * Email constructor
     *
     * @param string $email
     *
     * @throws ValueObjectException
     */
    public function __construct(string $email)
    {
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValueObjectException('Invalid email', ['email' => $email]);
        }

        parent::__construct($email);
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
