<?php

function noFlags($data, string $json)
{
    $encoded = json_encode($data);
    $literal = json_encode(['salt' => 'value']);
    $decoded = json_decode($json, true);
    $fullyQualified = \json_decode($json);
}

?>
-----
<?php

function noFlags($data, string $json)
{
    $encoded = json_encode($data, flags: JSON_THROW_ON_ERROR);
    $literal = json_encode(['salt' => 'value'], flags: JSON_THROW_ON_ERROR);
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $fullyQualified = \json_decode($json, flags: JSON_THROW_ON_ERROR);
}

?>
