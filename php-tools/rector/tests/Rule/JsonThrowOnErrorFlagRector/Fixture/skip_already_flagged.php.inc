<?php

function alreadyFlagged($data, string $json)
{
    $positional = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $named = json_encode($data, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $fullyQualifiedConstant = json_encode($data, \JSON_THROW_ON_ERROR);
}

?>
