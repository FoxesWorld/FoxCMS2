<?php

declare(strict_types=1);

/** Compiles validated Vue TPL bodies into same-origin ES modules without browser eval. */
final class ThemeRuntimeTplCompiler
{
    private const MAXIMUM_MODULE_BYTES = 2_097_152;
    private const TIMEOUT_SECONDS = 20;

    private string $themeName;
    private string $themeDirectory;
    private string $moduleDirectory;
    private string $compilerScript;
    private string $bridgeUrl;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Invalid runtime template theme name.');
        }
        $serverDirectory = ThemeRuntimeTplDocument::resolveDirectory(
            $templatesDirectory,
            $themeName,
            'assets/runtime/server',
        );
        $this->themeName = $themeName;
        $this->themeDirectory = dirname(dirname(dirname($serverDirectory)));
        $this->moduleDirectory = ThemeRuntimeTplDocument::resolveDirectory(
            $templatesDirectory,
            $themeName,
            'assets/runtime/templates',
        );
        $this->compilerScript = $serverDirectory . DIRECTORY_SEPARATOR . 'runtime-template-compiler.mjs';
        $this->bridgeUrl = '/templates/' . rawurlencode($themeName) . '/assets/runtime/vue-runtime.js';
    }

    /** @return array{moduleUrl:string,moduleFile:string} */
    public function ensure(string $id, int $revision, string $body): array
    {
        $path = $this->modulePath($id, $revision);
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            $this->publish($id, $revision, $body);
        }
        return [
            'moduleUrl' => $this->moduleUrl($id, $revision),
            'moduleFile' => basename($path),
        ];
    }

    public function publish(string $id, int $revision, string $body): void
    {
        $path = $this->modulePath($id, $revision);
        $this->ensureModuleDirectory();
        $module = $this->compile($id, $body);
        ThemeRuntimeTplDocument::write(
            $this->moduleDirectory,
            $path,
            $module,
            'runtime-template-module',
        );
    }

    public function cleanup(string $id, int $currentRevision): void
    {
        $id = $this->id($id);
        if (!is_dir($this->moduleDirectory) || is_link($this->moduleDirectory)) return;
        $current = basename($this->modulePath($id, $currentRevision));
        $modules = [];
        foreach (glob($this->moduleDirectory . DIRECTORY_SEPARATOR . $id . '.*.js') ?: [] as $path) {
            if (!is_file($path) || is_link($path)) continue;
            if (preg_match('/\.(\d+)\.js$/D', basename($path), $match) !== 1) continue;
            $modules[(int)$match[1]] = $path;
        }
        krsort($modules, SORT_NUMERIC);
        $retained = 0;
        foreach ($modules as $path) {
            if (basename($path) === $current || $retained < 4) {
                $retained++;
                continue;
            }
            @unlink($path);
        }
    }

    public function storageReady(): bool
    {
        if (!function_exists('proc_open') || !is_file($this->compilerScript) || !is_readable($this->compilerScript)) {
            return false;
        }
        try {
            $this->ensureModuleDirectory();
        } catch (Throwable) {
            return false;
        }
        return is_writable($this->moduleDirectory);
    }

    private function compile(string $id, string $body): string
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Runtime TPL compilation requires proc_open.');
        }
        if (!is_file($this->compilerScript) || !is_readable($this->compilerScript)) {
            throw new RuntimeException('Runtime TPL compiler script is unavailable.');
        }
        if ($body === '' || strlen($body) > ThemeRuntimeTplDocument::MAXIMUM_BYTES || str_contains($body, "\0")) {
            throw new InvalidArgumentException('Runtime TPL body is invalid.');
        }

        $node = trim((string)(getenv('FOXESCRAFT_NODE_BINARY') ?: 'node'));
        if ($node === '' || str_contains($node, "\0")) {
            throw new RuntimeException('Runtime TPL Node.js binary is invalid.');
        }
        $command = [
            $node,
            $this->compilerScript,
            '--id',
            $id,
            '--bridge-url',
            $this->bridgeUrl,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = @proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->themeDirectory,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the runtime TPL compiler.');
        }

        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        try {
            $written = 0;
            $length = strlen($body);
            while ($written < $length) {
                $count = fwrite($pipes[0], substr($body, $written));
                if (!is_int($count) || $count <= 0) throw new RuntimeException('Unable to stream TPL source to the compiler.');
                $written += $count;
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $deadline = microtime(true) + self::TIMEOUT_SECONDS;
            while (true) {
                $stdout .= (string)stream_get_contents($pipes[1]);
                $stderr .= (string)stream_get_contents($pipes[2]);
                if (strlen($stdout) > self::MAXIMUM_MODULE_BYTES || strlen($stderr) > self::MAXIMUM_MODULE_BYTES) {
                    proc_terminate($process);
                    throw new RuntimeException('Runtime TPL compiler output exceeded the safety limit.');
                }
                $status = proc_get_status($process);
                if (!is_array($status) || !($status['running'] ?? false)) {
                    $exitCode = (int)($status['exitcode'] ?? -1);
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('Runtime TPL compilation timed out.');
                }
                usleep(10_000);
            }
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
        } finally {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            $closed = proc_close($process);
            if ($exitCode < 0 && is_int($closed)) $exitCode = $closed;
        }

        if ($exitCode !== 0) {
            $diagnostic = trim(preg_replace('/\s+/u', ' ', $stderr) ?? $stderr);
            if ($diagnostic === '') $diagnostic = 'unknown compiler failure';
            throw new InvalidArgumentException('Runtime TPL compilation failed: ' . substr($diagnostic, 0, 1000));
        }
        return $this->validateModule($stdout, $id);
    }

    private function validateModule(string $module, string $id): string
    {
        if ($module === '' || strlen($module) > self::MAXIMUM_MODULE_BYTES || str_contains($module, "\0")) {
            throw new RuntimeException('Compiled runtime TPL module is invalid.');
        }
        foreach ([
            'from "' . $this->bridgeUrl . '"',
            'export function render(',
            'export const templateId = ' . json_encode($id, JSON_UNESCAPED_SLASHES),
        ] as $required) {
            if (!str_contains($module, $required)) {
                throw new RuntimeException('Compiled runtime TPL module failed integrity validation.');
            }
        }
        foreach (['new Function', 'eval(', 'sourceMappingURL=', '<script'] as $forbidden) {
            if (str_contains($module, $forbidden)) {
                throw new RuntimeException('Compiled runtime TPL module contains forbidden code.');
            }
        }
        return rtrim($module) . PHP_EOL;
    }

    private function modulePath(string $id, int $revision): string
    {
        $id = $this->id($id);
        if ($revision < 1 || $revision > 2_147_483_647) {
            throw new InvalidArgumentException('Invalid runtime TPL module revision.');
        }
        return $this->moduleDirectory . DIRECTORY_SEPARATOR . $id . '.' . $revision . '.js';
    }

    private function moduleUrl(string $id, int $revision): string
    {
        $this->modulePath($id, $revision);
        return '/templates/' . rawurlencode($this->themeName)
            . '/assets/runtime/templates/' . rawurlencode($id) . '.' . $revision . '.js?v=' . $revision;
    }

    private function ensureModuleDirectory(): void
    {
        if (is_link($this->moduleDirectory)) {
            throw new RuntimeException('Runtime TPL module directory cannot be a symbolic link.');
        }
        if (!is_dir($this->moduleDirectory)
            && !@mkdir($this->moduleDirectory, 0775, true)
            && !is_dir($this->moduleDirectory)) {
            throw new RuntimeException('Unable to create the runtime TPL module directory.');
        }
        if (!is_writable($this->moduleDirectory)) {
            throw new RuntimeException('Runtime TPL module directory is not writable.');
        }
    }

    private function id(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid runtime TPL module id.');
        }
        return $value;
    }
}
