<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Config;

use DaveLiddament\PhpstanEffectSystem\Config\EffectsConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EffectsConfigTest extends TestCase
{
    public function testStubRecordsAreBuiltForMethodsAndFunctions(): void
    {
        $config = EffectsConfig::fromParameters(
            [
                ['method' => '\GuzzleHttp\Client::request', 'effects' => ['slow', 'http']],
                ['function' => 'file_get_contents', 'effects' => ['io']],
            ],
            [],
            [],
            [],
        );

        self::assertCount(2, $config->stubRecords);

        [$method, $function] = $config->stubRecords;
        self::assertSame('guzzlehttp\client::request', $method->key);
        self::assertSame('GuzzleHttp\Client', $method->className);
        self::assertSame(['slow', 'http'], $method->effects);
        self::assertNull($method->file);

        self::assertSame('file_get_contents()', $function->key);
        self::assertNull($function->className);
        self::assertSame(['io'], $function->effects);
    }

    public function testStubWithBothMethodAndFunctionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('exactly one of "method" or "function"');

        EffectsConfig::fromParameters(
            [['method' => 'A::b', 'function' => 'c', 'effects' => ['io']]],
            [],
            [],
            [],
        );
    }

    public function testStubMethodWithoutClassSeparatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('"Fully\Qualified\ClassName::methodName"');

        EffectsConfig::fromParameters(
            [['method' => 'justAMethodName', 'effects' => ['io']]],
            [],
            [],
            [],
        );
    }

    public function testStubWithoutEffectsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('at least one effect');

        EffectsConfig::fromParameters(
            [['method' => 'A::b', 'effects' => []]],
            [],
            [],
            [],
        );
    }

    public function testStubEffectOutsideAllowedEffectsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("unknown effect 'htpp'");

        EffectsConfig::fromParameters(
            [['method' => 'A::b', 'effects' => ['htpp']]],
            [],
            ['http', 'slow'],
            [],
        );
    }

    public function testRuleWithEmptyEffectFreeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('at least one effect in "effectFree"');

        EffectsConfig::fromParameters(
            [],
            [['classPattern' => 'App\*', 'methodPattern' => '*', 'effectFree' => []]],
            [],
            [],
        );
    }

    public function testRuleEffectOutsideAllowedEffectsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("unknown effect 'slwo'");

        EffectsConfig::fromParameters(
            [],
            [['classPattern' => 'App\*', 'methodPattern' => '*', 'effectFree' => ['slwo']]],
            ['slow'],
            [],
        );
    }
}
