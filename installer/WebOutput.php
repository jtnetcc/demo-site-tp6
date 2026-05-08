<?php

namespace Installer;

class WebOutput extends Console
{
    private array $messages = [];

    public function __construct()
    {
        parent::__construct(['install.php']);
    }

    public function line(string $message = ''): void
    {
        if ($message === '') {
            return;
        }

        $this->messages[] = ['type' => 'line', 'message' => $message];
    }

    public function info(string $message): void
    {
        $this->messages[] = ['type' => 'info', 'message' => $message];
    }

    public function ok(string $message): void
    {
        $this->messages[] = ['type' => 'ok', 'message' => $message];
    }

    public function warn(string $message): void
    {
        $this->messages[] = ['type' => 'warn', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->messages[] = ['type' => 'error', 'message' => $message];
    }

    public function confirm(string $label, bool $default = false): bool
    {
        return $default;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
