<?php

namespace Core\Common\Infrastructure\Schedule;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
class WriteMessageOnAFileHandler
{
    public function __invoke(WriteMessageOnAFile $message) {
        file_put_contents('/tmp/scheduled/poc_file', $message->getMessage(), FILE_APPEND);
    }
}