<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

final class EffectSystemRuleTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule([], [], [], ['Tests\*', '*\Tests\*']);
    }

    public function testDirectCallToEffectMethodFromEffectFreeMethodIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/direct-violation.php'], [
            [
                "Method EffectTest\\DirectViolation\\Api::handle() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\DirectViolation\\Api::handle() -> EffectTest\\DirectViolation\\Db::query() (declares #[Effect('slow')]).",
                20,
            ],
        ]);
    }

    public function testTransitiveChainIsReportedAtSinkWithFullPath(): void
    {
        $this->analyse([__DIR__ . '/data/transitive-chain.php'], [
            [
                "Method EffectTest\\TransitiveChain\\HomeController::indexAction() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\TransitiveChain\\HomeController::indexAction() -> EffectTest\\TransitiveChain\\OrderRepository::findAll() -> EffectTest\\TransitiveChain\\OrderService::load() -> EffectTest\\TransitiveChain\\ApiClient::fetch() (declares #[Effect('slow')]).",
                36,
            ],
        ]);
    }

    public function testNoPathToEffectProducesNoErrors(): void
    {
        $this->analyse([__DIR__ . '/data/no-violation.php'], []);
    }

    public function testRecursionTerminatesAndEffectIsDetected(): void
    {
        $this->analyse([__DIR__ . '/data/recursion.php'], [
            [
                "Method EffectTest\\Recursion\\Walker::entry() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\Recursion\\Walker::entry() -> EffectTest\\Recursion\\Walker::stepA() -> EffectTest\\Recursion\\Walker::stepB() -> EffectTest\\Recursion\\Walker::slowLeaf() (declares #[Effect('slow')]).",
                12,
            ],
        ]);
    }

    public function testCallsInClosuresAndArrowFunctionsAttributeToEnclosingMethod(): void
    {
        $this->analyse([__DIR__ . '/data/closure-attribution.php'], [
            [
                "Method EffectTest\\ClosureAttribution\\Runner::run() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\ClosureAttribution\\Runner::run() -> EffectTest\\ClosureAttribution\\Files::write() (declares #[Effect('io')]).",
                29,
            ],
            [
                "Method EffectTest\\ClosureAttribution\\Runner::run() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\ClosureAttribution\\Runner::run() -> EffectTest\\ClosureAttribution\\Db::query() (declares #[Effect('slow')]).",
                29,
            ],
        ]);
    }

    public function testFreeFunctionsParticipateAsSourcesAndSinks(): void
    {
        $this->analyse([__DIR__ . '/data/functions.php'], [
            [
                "Function EffectTest\\Functions\\entry() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Functions\\entry() -> EffectTest\\Functions\\middle() -> EffectTest\\Functions\\readData() (declares #[Effect('io')]).",
                20,
            ],
        ]);
    }

    public function testInterfaceDispatchReportsEffectOfAnyKnownImplementation(): void
    {
        $this->analyse([__DIR__ . '/data/interface-dispatch.php'], [
            [
                "Method EffectTest\\InterfaceDispatch\\Alerter::alert() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\InterfaceDispatch\\Alerter::alert() -> EffectTest\\InterfaceDispatch\\SmsNotifier::send() (declares #[Effect('slow')]).",
                37,
            ],
        ]);
    }

    public function testEffectDeclaredOnInterfaceMethodItselfCounts(): void
    {
        $this->analyse([__DIR__ . '/data/interface-effect-decl.php'], [
            [
                "Method EffectTest\\InterfaceEffectDecl\\Client::fetch() is #[EffectFree('http')] but reaches effect 'http': EffectTest\\InterfaceEffectDecl\\Client::fetch() -> EffectTest\\InterfaceEffectDecl\\RemoteApi::call() (declares #[Effect('http')]).",
                25,
            ],
        ]);
    }

    public function testEffectFreeContractOnInterfaceIsInheritedByImplementations(): void
    {
        $this->analyse([__DIR__ . '/data/inherited-contract.php'], [
            [
                "Method EffectTest\\InheritedContract\\DbLoader::load() is #[EffectFree('slow')] (inherited from EffectTest\\InheritedContract\\Loader::load()) but reaches effect 'slow': EffectTest\\InheritedContract\\DbLoader::load() -> EffectTest\\InheritedContract\\DbLoader::slowQuery() (declares #[Effect('slow')]).",
                18,
            ],
        ]);
    }

    public function testCallsResolveToInheritedImplementations(): void
    {
        $this->analyse([__DIR__ . '/data/inherited-method-resolution.php'], [
            [
                "Method EffectTest\\InheritedMethodResolution\\Consumer::viaParentType() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\InheritedMethodResolution\\Consumer::viaParentType() -> EffectTest\\InheritedMethodResolution\\CachedRepo::load() (declares #[Effect('slow')]).",
                31,
            ],
            [
                "Method EffectTest\\InheritedMethodResolution\\Consumer::viaGrandChild() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\InheritedMethodResolution\\Consumer::viaGrandChild() -> EffectTest\\InheritedMethodResolution\\CachedRepo::load() (declares #[Effect('slow')]).",
                37,
            ],
        ]);
    }

    public function testImplementationsInExcludedNamespacesDoNotContributeEffects(): void
    {
        $this->analyse([__DIR__ . '/data/excluded-implementations.php'], [
            [
                "Method EffectTest\\ExcludedImplementations\\App::publishViaPublisher() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\ExcludedImplementations\\App::publishViaPublisher() -> EffectTest\\ExcludedImplementations\\ProdPublisher::publish() (declares #[Effect('slow')]).",
                43,
            ],
        ]);
    }

    public function testOnlyTheRouteReachingTheSlowMethodIsReported(): void
    {
        // A::slowRoute() and A::fastRoute() both go A -> B -> C, but only one
        // ends at the C method that declares the effect.
        $this->analyse([__DIR__ . '/data/method-level-precision.php'], [
            [
                "Method EffectTest\\MethodLevelPrecision\\A::slowRoute() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\MethodLevelPrecision\\A::slowRoute() -> EffectTest\\MethodLevelPrecision\\B::viaSlow() -> EffectTest\\MethodLevelPrecision\\C::slow() (declares #[Effect('slow')]).",
                47,
            ],
        ]);
    }

    public function testOnlyTheSlowRouteIsReportedThroughAnInterfaceAndTestDoublesAreIgnored(): void
    {
        // The code only references the interface C; the effect is declared on
        // the production implementation. The Tests\FakeC double is slow in
        // both methods and must not make fastRoute() a violation.
        $this->analyse([__DIR__ . '/data/interface-method-precision.php'], [
            [
                "Method EffectTest\\InterfaceMethodPrecision\\A::slowRoute() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\InterfaceMethodPrecision\\A::slowRoute() -> EffectTest\\InterfaceMethodPrecision\\B::viaSlow() -> EffectTest\\InterfaceMethodPrecision\\ProdC::slow() (declares #[Effect('slow')]).",
                54,
            ],
        ]);
    }

    public function testInterfaceContractAppliesToImplementationInheritedFromParentClass(): void
    {
        // Child implements Runner but inherits run() from Base, which knows
        // nothing about Runner. Base::run() is still what fulfils the contract.
        $this->analyse([__DIR__ . '/data/inherited-implementation-contract.php'], [
            [
                "Method EffectTest\\InheritedImplementationContract\\Base::run() is #[EffectFree('slow')] (inherited from EffectTest\\InheritedImplementationContract\\Runner::run() via EffectTest\\InheritedImplementationContract\\Child) but reaches effect 'slow': EffectTest\\InheritedImplementationContract\\Base::run() -> EffectTest\\InheritedImplementationContract\\Db::query() (declares #[Effect('slow')]).",
                27,
            ],
        ]);
    }

    public function testPrivateMethodsDoNotInheritContracts(): void
    {
        $this->analyse([__DIR__ . '/data/private-method-contract.php'], [
            [
                "Method EffectTest\\PrivateMethodContract\\ChildService::hook() is #[EffectFree('slow')] (inherited from EffectTest\\PrivateMethodContract\\ParentService::hook()) but reaches effect 'slow': EffectTest\\PrivateMethodContract\\ChildService::hook() -> EffectTest\\PrivateMethodContract\\Db::query() (declares #[Effect('slow')]).",
                41,
            ],
        ]);
    }

    public function testEffectAndEffectFreeOnSameMethodIsAContradiction(): void
    {
        $this->analyse([__DIR__ . '/data/contradiction.php'], [
            [
                "Method EffectTest\\Contradiction\\BadApi::get() declares #[Effect('slow')] but is #[EffectFree('slow')] (inherited from EffectTest\\Contradiction\\Api::get()).",
                18,
            ],
            [
                "Method EffectTest\\Contradiction\\SelfContradiction::both() declares #[Effect('io')] but is #[EffectFree('io')].",
                26,
            ],
        ]);
    }

    public function testHandlesEffectBlocksPropagationButNotOwnEffect(): void
    {
        $this->analyse([__DIR__ . '/data/handles-effect.php'], [
            [
                "Method EffectTest\\HandlesEffect\\Api::stillSlow() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\HandlesEffect\\Api::stillSlow() -> EffectTest\\HandlesEffect\\StillSlowCache::warm() (declares #[Effect('slow')]).",
                46,
            ],
        ]);
    }

    public function testTraitMethodsAreAnalysedPerUsingClass(): void
    {
        $this->analyse([__DIR__ . '/data/trait-methods.php'], [
            [
                "Method EffectTest\\TraitMethods\\SlowService::guarded() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\TraitMethods\\SlowService::guarded() -> EffectTest\\TraitMethods\\SlowService::doWork() (declares #[Effect('slow')]).",
                12,
            ],
        ]);
    }

    public function testFirstClassCallableRecordsAnEdge(): void
    {
        $this->analyse([__DIR__ . '/data/first-class-callable.php'], [
            [
                "Method EffectTest\\FirstClassCallable\\Api::handle() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\FirstClassCallable\\Api::handle() -> EffectTest\\FirstClassCallable\\Db::query() (declares #[Effect('slow')]).",
                20,
            ],
        ]);
    }

    public function testVariableCallablesAreNotTracked(): void
    {
        $this->analyse([__DIR__ . '/data/dynamic-callable.php'], []);
    }

    public function testShortestPathIsReportedWhenMultiplePathsExist(): void
    {
        $this->analyse([__DIR__ . '/data/shortest-path.php'], [
            [
                "Method EffectTest\\ShortestPath\\Sink::entry() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\ShortestPath\\Sink::entry() -> EffectTest\\ShortestPath\\Leaf::slow() (declares #[Effect('slow')]).",
                36,
            ],
        ]);
    }

    public function testRepeatableAttributesDeclareMultipleEffectsAndContracts(): void
    {
        $this->analyse([__DIR__ . '/data/repeatable-attributes.php'], [
            [
                "Method EffectTest\\RepeatableAttributes\\Worker::process() is #[EffectFree('http')] but reaches effect 'http': EffectTest\\RepeatableAttributes\\Worker::process() -> EffectTest\\RepeatableAttributes\\Gateway::send() (declares #[Effect('http')]).",
                21,
            ],
            [
                "Method EffectTest\\RepeatableAttributes\\Worker::both() is #[EffectFree('http')] but reaches effect 'http': EffectTest\\RepeatableAttributes\\Worker::both() -> EffectTest\\RepeatableAttributes\\Gateway::send() (declares #[Effect('http')]).",
                27,
            ],
            [
                "Method EffectTest\\RepeatableAttributes\\Worker::both() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\RepeatableAttributes\\Worker::both() -> EffectTest\\RepeatableAttributes\\Gateway::send() (declares #[Effect('slow')]).",
                27,
            ],
        ]);
    }
}
