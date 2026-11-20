<?php

function existingFlags($data, string $json, int $options)
{
    $single = json_encode($data, JSON_UNESCAPED_UNICODE);
    $combined = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $zero = json_encode($data, 0);
    $variable = json_encode($data, $options);
    $positional = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
    $named = json_decode($json, flags: JSON_BIGINT_AS_STRING);
}

?>
-----
<?php

function existingFlags($data, string $json, int $options)
{
    $single = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $combined = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $zero = json_encode($data, JSON_THROW_ON_ERROR);
    $variable = json_encode($data, $options | JSON_THROW_ON_ERROR);
    $positional = json_decode($json, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
    $named = json_decode($json, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
}

?>
