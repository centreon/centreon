<?php

function outerScope(string $json)
{
    $decode = static fn (string $input) => json_decode($input, true);
    $decoded = $decode($json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

function siblingScope(string $json)
{
    return json_decode($json, true);
}

final class SiblingClass
{
    public function decode(string $json): mixed
    {
        return json_decode($json, true);
    }
}

$topLevel = json_encode([]);

?>
-----
<?php

function outerScope(string $json)
{
    $decode = static fn (string $input) => json_decode($input, true);
    $decoded = $decode($json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

function siblingScope(string $json)
{
    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

final class SiblingClass
{
    public function decode(string $json): mixed
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}

$topLevel = json_encode([], flags: JSON_THROW_ON_ERROR);

?>
