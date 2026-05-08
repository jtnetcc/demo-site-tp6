<?php

namespace Installer;

class Console
{
    private array $options;

    public function __construct(array $argv)
    {
        $this->options = $this->parseOptions($argv);
    }

    public function option(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    public function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function info(string $message): void
    {
        $this->line('[INFO] ' . $message);
    }

    public function ok(string $message): void
    {
        $this->line('[OK] ' . $message);
    }

    public function warn(string $message): void
    {
        $this->line('[WARN] ' . $message);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    }

    public function ask(string $label, ?string $default = null, bool $required = true): string
    {
        while (true) {
            $suffix = $default !== null && $default !== '' ? ' [' . $default . ']' : '';
            fwrite(STDOUT, $label . $suffix . ': ');
            $value = trim((string) fgets(STDIN));

            if ($value === '' && $default !== null) {
                $value = $default;
            }

            if ($value !== '' || !$required) {
                return $value;
            }

            $this->warn('该项不能为空');
        }
    }

    public function askPassword(string $label, ?string $default = null): string
    {
        $value = $this->ask($label, $default, false);

        return $value;
    }

    public function confirm(string $label, bool $default = false): bool
    {
        $hint = $default ? 'Y/n' : 'y/N';
        fwrite(STDOUT, $label . ' [' . $hint . ']: ');
        $value = strtolower(trim((string) fgets(STDIN)));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['y', 'yes'], true);
    }

    private function parseOptions(array $argv): array
    {
        $options = [];

        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }

            $arg = substr($arg, 2);

            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', $arg, 2);
                $options[$key] = $value;
            } else {
                $options[$arg] = '1';
            }
        }

        return $options;
    }
}
