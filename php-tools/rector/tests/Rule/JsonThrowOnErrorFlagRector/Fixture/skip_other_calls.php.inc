<?php

function otherCalls($serializer, $data)
{
    $method = $serializer->json_encode($data);
    $static = Serializer::json_decode($data);
    $other = serialize($data);
}

?>
