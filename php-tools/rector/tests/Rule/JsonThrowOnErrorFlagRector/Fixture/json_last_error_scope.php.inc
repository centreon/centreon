<?php

function jsonLastErrorScope(string $json)
{
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

?>
-----
<?php

function jsonLastErrorScope(string $json)
{
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

?>
