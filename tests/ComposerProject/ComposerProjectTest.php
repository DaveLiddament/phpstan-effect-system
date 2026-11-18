<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\ComposerProject;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Installs this package into a consumer project in e2e/ the way a user would,
 * with Composer symlinking it from a path repository, then runs that project's
 * PHPStan and checks the JSON output. This covers the packaging that the other
 * tests skip. Both projects analyse e2e/src with e2e/effects.neon and differ
 * only in how the extension is registered:
 *
 * - manual-include: phpstan.neon includes vendor/.../extension.neon.
 * - extension-installer: phpstan/extension-installer registers it from
 *   extra.phpstan in this package's composer.json.
 *
 * Needs `composer` on PATH and network access, so it is not in the default
 * test suite: run it with `vendor/bin/phpunit --testsuite composer-project`.
 */
final class ComposerProjectTest extends TestCase
{
    #[TestWith(['manual-include'], 'manual-include')]
    #[TestWith(['extension-installer'], 'extension-installer')]
    public function testInstalledExtensionReportsViolations(string $project): void
    {
        $sourceDir = dirname(__DIR__, 2) . '/e2e/src';
        $projectDir = dirname(__DIR__, 2) . '/e2e/' . $project;

        // Start clean: with a lock file already present, Composer keeps the
        // package metadata it recorded for this commit, so changes to
        // composer.json would go unnoticed.
        $this->runCommand($projectDir, ['rm', '-rf', 'vendor', 'composer.lock', 'tmp'], 0);
        $this->runCommand($projectDir, ['composer', 'update', '--no-interaction', '--no-progress'], 0);
        $stdout = $this->runCommand($projectDir, [PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '-c', 'phpstan.neon', '--error-format=json', '--no-progress'], 1);

        $expected = [
            'totals' => ['errors' => 0, 'file_errors' => 2],
            'files' => [
                $sourceDir . '/Controller/OrderController.php' => [
                    'errors' => 1,
                    'messages' => [[
                        'message' => "Method E2eApp\\Controller\\OrderController::showAction() is required to be effect-free for 'slow' by the effects rule (classPattern: 'E2eApp\\Controller\\*', methodPattern: '*Action') but reaches effect 'slow': E2eApp\\Controller\\OrderController::showAction() -> E2eApp\\OrderService::load() -> E2eApp\\ApiClient::fetch() (declares #[Effect('slow')]).",
                        'line' => 12,
                        'ignorable' => true,
                        'identifier' => 'effects.violation',
                    ]],
                ],
                $sourceDir . '/PriceCalculator.php' => [
                    'errors' => 1,
                    'messages' => [[
                        'message' => "Method E2eApp\\PriceCalculator::total() is #[EffectFree('slow')] but reaches effect 'slow': E2eApp\\PriceCalculator::total() -> E2eApp\\OrderService::load() -> E2eApp\\ApiClient::fetch() (declares #[Effect('slow')]).",
                        'line' => 12,
                        'ignorable' => true,
                        'identifier' => 'effects.violation',
                    ]],
                ],
            ],
            'errors' => [],
        ];

        self::assertSame($expected, json_decode($stdout, true), "Unexpected PHPStan output:\n" . $stdout);
    }

    /**
     * @param list<string> $command
     */
    private function runCommand(string $cwd, array $command, int $expectedExitCode): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if ($process === false) {
            throw new RuntimeException('Failed to start ' . implode(' ', $command));
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame($expectedExitCode, $exitCode, implode(' ', $command) . " exited with {$exitCode}. Output:\n" . $stdout . $stderr);

        return $stdout;
    }
}
