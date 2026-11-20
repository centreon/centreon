<?php

function unpackedAndCallable(array $arguments, array $items)
{
    $unpacked = json_encode(...$arguments);
    $callable = array_map(json_encode(...), $items);
}

?>
