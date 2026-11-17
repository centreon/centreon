<?php

function argumentPositions(string $json)
{
    $allPositional = json_decode($json, true, 512);
    $namedZero = json_decode($json, flags: 0);
    $flagsBeforeDepth = json_decode($json, null, flags: JSON_BIGINT_AS_STRING, depth: 3);
}

?>
-----
<?php

function argumentPositions(string $json)
{
    $allPositional = json_decode($json, true, 512, flags: JSON_THROW_ON_ERROR);
    $namedZero = json_decode($json, flags: JSON_THROW_ON_ERROR);
    $flagsBeforeDepth = json_decode($json, null, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR, depth: 3);
}

?>
