<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Graph;

use DaveLiddament\PhpstanEffectSystem\Graph\CallGraphBuilder;
use DaveLiddament\PhpstanEffectSystem\Graph\ClassHierarchy;
use DaveLiddament\PhpstanEffectSystem\Graph\DeclarationRecord;
use DaveLiddament\PhpstanEffectSystem\Graph\Declarations;
use PHPUnit\Framework\TestCase;

final class CallGraphBuilderTest extends TestCase
{
    /**
     * Pins the fnmatch flag behavior: without FNM_NOESCAPE the backslashes in
     * patterns like 'Tests\*' escape the wildcard and nothing is ever
     * excluded.
     */
    public function testDispatchExpansionSkipsExcludedNamespacesAndKeepsOthers(): void
    {
        $declarations = new Declarations();
        $declarations->add(new DeclarationRecord('app\iface::run', 'App\Iface', 'run', 'f.php', 1, [], [], [], true));
        $declarations->add(new DeclarationRecord('app\prod::run', 'App\Prod', 'run', 'f.php', 2, [], [], [], false));
        $declarations->add(new DeclarationRecord('app\tests\fake::run', 'App\Tests\Fake', 'run', 'f.php', 3, [], [], [], false));
        $declarations->add(new DeclarationRecord('tests\otherfake::run', 'Tests\OtherFake', 'run', 'f.php', 4, [], [], [], false));

        $hierarchy = ClassHierarchy::fromCollectedRecords([
            ['class' => 'App\Iface', 'parents' => [], 'interfaces' => [], 'isInterface' => true, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
            ['class' => 'App\Prod', 'parents' => [], 'interfaces' => ['App\Iface'], 'isInterface' => false, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
            ['class' => 'App\Tests\Fake', 'parents' => [], 'interfaces' => ['App\Iface'], 'isInterface' => false, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
            ['class' => 'Tests\OtherFake', 'parents' => [], 'interfaces' => ['App\Iface'], 'isInterface' => false, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
        ]);

        $builder = new CallGraphBuilder(['Tests\*', '*\Tests\*']);
        $graph = $builder->build([
            [
                'caller' => 'app\caller::go',
                'line' => 10,
                'callees' => [
                    ['key' => 'app\iface::run', 'calledClass' => 'App\Iface', 'method' => 'run', 'dispatch' => true],
                ],
            ],
        ], $hierarchy, $declarations);

        $callees = $graph->calleesOf('app\caller::go');
        sort($callees);

        self::assertSame(['app\iface::run', 'app\prod::run'], $callees);
    }

    public function testInheritedImplementationsResolveThroughParentChain(): void
    {
        $declarations = new Declarations();
        $declarations->add(new DeclarationRecord('app\base::run', 'App\Base', 'run', 'f.php', 1, [], [], [], false));

        $hierarchy = ClassHierarchy::fromCollectedRecords([
            ['class' => 'App\Base', 'parents' => [], 'interfaces' => [], 'isInterface' => false, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
            ['class' => 'App\Child', 'parents' => ['App\Base'], 'interfaces' => [], 'isInterface' => false, 'isAbstract' => false, 'isFinal' => false, 'isAnonymous' => false],
        ]);

        $graph = (new CallGraphBuilder([]))->build([
            [
                'caller' => 'app\caller::go',
                'line' => 5,
                'callees' => [
                    ['key' => 'app\base::run', 'calledClass' => 'App\Base', 'method' => 'run', 'dispatch' => true],
                ],
            ],
        ], $hierarchy, $declarations);

        // App\Child inherits run() from App\Base, so the expansion resolves
        // back to the same declaration — a single deduplicated edge.
        self::assertSame(['app\base::run'], $graph->calleesOf('app\caller::go'));
    }
}
