<?php

function outerScope(string $json)
{
    $decoded = json_decode($json, true);
    $encode = static fn (array $data) => json_encode($data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return [$decoded, $encode];
}

function siblingScope(string $json)
{
    return json_decode($json, true);
}

$topLevel = json_encode([]);

?>
-----
<?php

function outerScope(string $json)
{
    $decoded = json_decode($json, true);
    $encode = static fn (array $data) => json_encode($data, flags: JSON_THROW_ON_ERROR);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return [$decoded, $encode];
}

function siblingScope(string $json)
{
    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

$topLevel = json_encode([], flags: JSON_THROW_ON_ERROR);

?>
