<?php

function jsonLastErrorScope(string $json)
{
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

final class JsonLastErrorMsgScope
{
    public function encode(array $data): string
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw new \RuntimeException(json_last_error_msg());
        }

        return $encoded;
    }
}

function checkInsideClosure(string $json)
{
    $decoded = json_decode($json, true);
    $isValid = static fn (): bool => json_last_error() === JSON_ERROR_NONE;

    return $isValid() ? $decoded : null;
}

?>
