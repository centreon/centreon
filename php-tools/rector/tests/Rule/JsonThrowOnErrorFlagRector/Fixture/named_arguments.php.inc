<?php

function namedArguments(string $json)
{
    $decoded = json_decode(json: $json, associative: true);
    $depth = json_decode($json, depth: 10);
}

?>
-----
<?php

function namedArguments(string $json)
{
    $decoded = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
    $depth = json_decode($json, depth: 10, flags: JSON_THROW_ON_ERROR);
}

?>
