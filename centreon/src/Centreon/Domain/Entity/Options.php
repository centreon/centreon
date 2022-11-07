<?php

namespace Centreon\Domain\Entity;

class Options
{
<<<<<<< HEAD
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $value;

    /**
     * @param string $key
     */
=======

    private $key;
    private $value;

>>>>>>> centreon/dev-21.10.x
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getKey(): string
    {
        return $this->key;
    }

<<<<<<< HEAD
    /**
     * @param string $value
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

<<<<<<< HEAD
    /**
     * @return string|null
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getValue(): ?string
    {
        return $this->value;
    }
}
