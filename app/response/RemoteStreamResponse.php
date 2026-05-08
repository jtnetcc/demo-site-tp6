<?php

namespace app\response;

use think\Response;

class RemoteStreamResponse extends Response
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
        $url = (string) ($this->data['url'] ?? '');
        $range = (string) ($this->data['range'] ?? '');
        $headers = "User-Agent: Mozilla/5.0\r\nAccept: */*\r\n";

        if ($range !== '') {
            $headers .= 'Range: ' . $range . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'ignore_errors' => true,
                'header' => $headers,
            ],
        ]);
        $handle = @fopen($url, 'rb', false, $context);

        if (!$handle) {
            return;
        }

        while (!feof($handle)) {
            $chunk = fread($handle, $this->chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        }

        fclose($handle);
    }
}
