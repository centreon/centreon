<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Core\Security\Token\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\TrimmedString;

abstract class NewToken
{
    protected readonly \DateTimeInterface $creationDate;

    protected string $shortName;

    /**
     * @param TrimmedString $name
     * @param int $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface|null $expirationDate
     * @param TokenTypeEnum $type
     *
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function __construct(
        private readonly TrimmedString $name,
        private readonly int $creatorId,
        private readonly TrimmedString $creatorName,
        private readonly ?\DateTimeInterface $expirationDate,
        private readonly TokenTypeEnum $type,
    )
    {
        $this->creationDate = new \DateTimeImmutable();
        if ($this->expirationDate !== null) {
            Assertion::minDate($this->expirationDate, $this->creationDate, "{$this->shortName}::expirationDate");
        }
        Assertion::notEmptyString($this->name->value, "{$this->shortName}::name");
        Assertion::maxLength($this->name->value, Token::MAX_TOKEN_NAME_LENGTH, "{$this->shortName}::name");
        Assertion::positiveInt($this->creatorId, "{$this->shortName}::creatorId");
        Assertion::notEmptyString($this->creatorName->value, "{$this->shortName}::creatorName");
        Assertion::maxLength($this->creatorName->value, Token::MAX_USER_NAME_LENGTH, "{$this->shortName}::creatorName");
    }

    abstract public function getToken(): string;

    public function getCreationDate(): \DateTimeInterface
    {
        return $this->creationDate;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getCreatorId(): int
    {
        return $this->creatorId;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName->value;
    }

    public function getType(): TokenTypeEnum
    {
        return $this->type;
    }

    abstract protected function generateToken(): void;
}
