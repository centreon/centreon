<?php

function flagsWithLowerPrecedence($data, string $json, bool $pretty, ?int $options)
{
    $ternary = json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
    $shortTernary = json_encode($data, $options ?: JSON_PRETTY_PRINT);
    $coalesce = json_decode($json, flags: $options ?? 0);
}

?>
-----
<?php

function flagsWithLowerPrecedence($data, string $json, bool $pretty, ?int $options)
{
    $ternary = json_encode($data, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_THROW_ON_ERROR);
    $shortTernary = json_encode($data, ($options ?: JSON_PRETTY_PRINT) | JSON_THROW_ON_ERROR);
    $coalesce = json_decode($json, flags: ($options ?? 0) | JSON_THROW_ON_ERROR);
}

?>
