<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\EndToEnd;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Runs the real phpstan binary (with the extension loaded via extension.neon)
 * against a small fixture app, exercising the full pipeline: parametersSchema,
 * collector wiring, cross-file error attribution — and result-cache
 * invalidation across runs that mutate a deep leaf.
 */
final class EndToEndTest extends TestCase
{
    private const CHECKOUT_VIOLATION = "Method FixtureApp\\App\\CheckoutController::checkout() is #[EffectFree('slow')] but reaches effect 'slow': FixtureApp\\App\\CheckoutController::checkout() -> FixtureApp\\Infra\\StripeGateway::charge() -> FixtureApp\\Infra\\SlowHttp::post() (declares #[Effect('slow')]).";
    private const SUMMARY_VIOLATION = "Method FixtureApp\\App\\CheckoutController::summary() is #[EffectFree('slow')] but reaches effect 'slow': FixtureApp\\App\\CheckoutController::summary() -> FixtureApp\\App\\Helper::format() (declares #[Effect('slow')]).";

    private const CLEAN_HELPER = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace FixtureApp\App;

        class Helper
        {
            public function format(): void
            {
            }
        }

        PHP;

    private const SLOW_HELPER = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace FixtureApp\App;

        use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;

        class Helper
        {
            #[Effect('slow')]
            public function format(): void
            {
            }
        }

        PHP;

    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/phpstan-effects-e2e-' . bin2hex(random_bytes(6));
        $this->copyDirectory(__DIR__ . '/fixture', $this->workDir);

        $repoRoot = dirname(__DIR__, 2);
        $neon = <<<NEON
            includes:
            \t- {$repoRoot}/extension.neon

            parameters:
            \tlevel: 0
            \tpaths:
            \t\t- src
            \tbootstrapFiles:
            \t\t- {$repoRoot}/vendor/autoload.php
            \ttmpDir: {$this->workDir}/phpstan-tmp

            NEON;
        file_put_contents($this->workDir . '/phpstan.neon', $neon);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    public function testWholeProjectAnalysisWithResultCacheInvalidation(): void
    {
        // Run 1: fresh analysis — only checkout() violates (via dispatch on
        // the PaymentGateway interface, three hops deep).
        $this->assertViolations([self::CHECKOUT_VIOLATION], $this->runPhpstan());

        // Run 2: nothing changed — the result cache replays the same errors.
        $this->assertViolations([self::CHECKOUT_VIOLATION], $this->runPhpstan());

        // Run 3: a previously clean leaf gains #[Effect('slow')]. The cache
        // must invalidate through to the distant sink summary().
        file_put_contents($this->workDir . '/src/App/Helper.php', self::SLOW_HELPER);
        $this->assertViolations([self::CHECKOUT_VIOLATION, self::SUMMARY_VIOLATION], $this->runPhpstan());

        // Run 4: revert the leaf — summary() is clean again.
        file_put_contents($this->workDir . '/src/App/Helper.php', self::CLEAN_HELPER);
        $this->assertViolations([self::CHECKOUT_VIOLATION], $this->runPhpstan());

        // Run 5: touch a file on no effect path — errors unchanged.
        file_put_contents($this->workDir . '/src/App/Unrelated.php', "// touched\n", FILE_APPEND);
        $this->assertViolations([self::CHECKOUT_VIOLATION], $this->runPhpstan());
    }

    /**
     * @param list<string> $expectedMessages
     * @param array{exitCode: int, stdout: string, stderr: string} $result
     */
    private function assertViolations(array $expectedMessages, array $result): void
    {
        self::assertSame(1, $result['exitCode'], "phpstan should exit 1 (errors found). Output:\n" . $result['stdout'] . $result['stderr']);

        $json = json_decode($result['stdout'], true);
        if (!is_array($json)) {
            self::fail('phpstan should emit JSON. Output: ' . $result['stdout']);
        }

        $files = $json['files'] ?? [];
        if (!is_array($files)) {
            self::fail('Unexpected JSON shape: ' . $result['stdout']);
        }

        $actualMessages = [];
        foreach ($files as $fileErrors) {
            if (!is_array($fileErrors) || !is_array($fileErrors['messages'] ?? null)) {
                self::fail('Unexpected JSON shape: ' . $result['stdout']);
            }
            foreach ($fileErrors['messages'] as $message) {
                if (!is_array($message) || !is_string($message['message'] ?? null)) {
                    self::fail('Unexpected JSON shape: ' . $result['stdout']);
                }
                self::assertSame('effects.violation', $message['identifier'] ?? null);
                $actualMessages[] = $message['message'];
            }
        }
        sort($actualMessages);
        sort($expectedMessages);

        self::assertSame($expectedMessages, $actualMessages);
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runPhpstan(): array
    {
        $repoRoot = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            $repoRoot . '/vendor/bin/phpstan',
            'analyse',
            '-c', $this->workDir . '/phpstan.neon',
            '--error-format=json',
            '--no-progress',
        ];

        // Strip CI markers so PHPStan keeps its result cache enabled — the
        // whole point of the multi-run assertions.
        $env = getenv();
        foreach (['CI', 'CONTINUOUS_INTEGRATION', 'GITHUB_ACTIONS', 'GITLAB_CI', 'TRAVIS', 'CIRCLECI', 'BUILDKITE'] as $ciVar) {
            unset($env[$ciVar]);
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->workDir, $env);
        if ($process === false) {
            throw new RuntimeException('Failed to start phpstan');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }

    private function copyDirectory(string $from, string $to): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        if (!mkdir($to, 0777, true) && !is_dir($to)) {
            throw new RuntimeException('Cannot create ' . $to);
        }

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $target = $to . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!mkdir($target) && !is_dir($target)) {
                    throw new RuntimeException('Cannot create ' . $target);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
