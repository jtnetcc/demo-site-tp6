<?php

namespace app\response;

use think\Response;

class StreamFileResponse extends Response
{
    private int $chunkSize = 1048576;

    public function __construct(array $data = [], int $code = 200)
    {
        $this->init($data, $code);
    }

    protected function output($data)
    {
        return '';
    }

    protected function sendData(string $data): void
    {
        $path = (string) ($this->data['path'] ?? '');
        $start = (int) ($this->data['start'] ?? 0);
        $length = (int) ($this->data['length'] ?? 0);
        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return;
        }

        if ($start > 0) {
            fseek($handle, $start);
        }

        while ($length > 0 && !feof($handle)) {
            $readLength = min($this->chunkSize, $length);
            $chunk = fread($handle, $readLength);

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $length -= strlen($chunk);

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        }

        fclose($handle);
    }
}
