# PHPStan Effect System

A PHPStan extension implementing a lightweight effect system for PHP.

Methods declare effects (e.g. `slow`, `io`, `db`) via attributes **at the source
only**. The extension infers transitive effect propagation through the whole
call graph — no annotations on intermediate callers — and enforces
`#[EffectFree]` contracts at the boundaries you declare.

This is deliberately **not** the Java checked-exceptions model (declared
propagation at every level). Effects are declared at leaves; everything else is
inferred. Enforcement happens only at declared sinks.

```php
use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class ApiClient
{
    #[Effect('slow')]                 // the source: this method IS slow
    public function fetch(): void { /* ... */ }
}

class OrderService
{
    public function load(): void      // nothing declared here...
    {
        (new ApiClient())->fetch();
    }
}

class HomeController
{
    #[EffectFree('slow')]             // the sink: must never reach 'slow'
    public function indexAction(): void
    {
        (new OrderService())->load(); // ERROR reported here
    }
}
```

```
Method HomeController::indexAction() is #[EffectFree('slow')] but reaches effect 'slow':
HomeController::indexAction() -> OrderService::load() -> ApiClient::fetch() (declares #[Effect('slow')]).
```

Effect names are arbitrary strings — invent whatever taxonomy fits your
architecture (`slow`, `io`, `http`, `db`, `nondeterministic`, ...).

## Installation

```bash
composer require --dev dave-liddament/phpstan-effect-system
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer)
the extension registers itself. Otherwise include it manually:

```neon
includes:
    - vendor/dave-liddament/phpstan-effect-system/extension.neon
```

The attribute classes have zero dependencies. Installing the package in
`require-dev` is safe even though the attributes appear in production code:
PHP never autoloads an attribute class unless something reflects on it.

Requires PHP >= 8.3 and PHPStan 2.x.

## The attributes

All three target methods and functions, and are repeatable.

| Attribute | Meaning |
|---|---|
| `#[Effect('slow')]` | This method **has** the effect (a source). |
| `#[EffectFree('slow')]` | This method must not transitively reach the effect (a sink). Valid on interface/abstract methods; the contract applies to every implementation and override, and cannot be dropped. |
| `#[HandlesEffect('slow')]` | This method **discharges** the effect: callees' `slow` effects do not propagate through it (e.g. a caching wrapper). An `#[Effect('slow')]` declared on the same method still counts. |

## Semantics

- **Propagation** — effects flow from callee to caller, transitively, through
  the inferred call graph. Recursion and mutual recursion are handled.
- **HandlesEffect** — blocks propagation of that effect from callees. It does
  not cancel an `Effect` of the same name declared on the same method.
- **EffectFree inheritance** — a contract on an interface or parent method
  applies to every implementation/override; there is no syntax to drop it.
  Violations are reported at the implementation.
- **Contradiction** — declaring `Effect('x')` on a method whose own or
  inherited contracts include `EffectFree('x')` is a dedicated error.
- **Dynamic dispatch** — a call through an interface/abstract/parent type is
  treated as the union of all known implementations (closed-world). Effects
  declared on the interface method itself also count. Implementations in
  namespaces matching `excludeImplementationsFrom` (default: `Tests\*`,
  `*\Tests\*`) are excluded, so test doubles never pollute production
  dispatch.
- **Closures / arrow functions** — calls inside inline closures count as calls
  of the enclosing method.
- **First-class callables** — `$obj->method(...)` records an edge at the
  creation site (conservative: creating the callable counts as reaching it).
- **Traits** — trait methods are analysed per using class; effects and
  contracts apply per class.
- **Constructors** — `new Foo()` is an edge to `Foo::__construct()`.

## Configuration

All configuration lives under `parameters.effects` and is optional. The neon
schema is validated, so typos in the config fail fast.

```neon
parameters:
    effects:
        # Effects for code you don't own (vendor). Sources only; small list.
        stubs:
            - { method: 'GuzzleHttp\Client::request', effects: ['slow', 'http'] }
            - { function: 'file_get_contents', effects: ['io'] }

        # Pattern-based sinks: architectural sweeps without touching code.
        # Patterns are fnmatch-style, case-insensitive.
        rules:
            - { classPattern: 'App\Controller\*', methodPattern: '*Action', effectFree: ['slow'] }

        # Optional typo protection: error on any effect name not listed.
        # Empty list (default) disables the check.
        allowedEffects: ['slow', 'io', 'http', 'db']

        # Implementations in these namespaces are ignored when expanding
        # dynamic dispatch (test doubles, fakes, mocks).
        excludeImplementationsFrom:
            - 'Tests\*'
            - '*\Tests\*'
```

Notes on stubs:

- The `method` must name the class that **declares** the method (calls made
  through subclasses resolve to the declaring class).
- Stubs apply only to unanalysed code; a stub for a method in your analysed
  paths is ignored (the analysed declaration wins).

## Errors

| Identifier | Reported when | Location |
|---|---|---|
| `effects.violation` | An `EffectFree` method (attribute, inherited, or pattern rule) transitively reaches the effect. The message includes a shortest call path. | Sink method's declaration |
| `effects.contradiction` | A method declares `Effect('x')` while also being contractually `EffectFree('x')`. | The declaring method |
| `effects.unknownEffect` | An attribute uses an effect name outside `allowedEffects` (when configured). Unknown names in the neon config itself throw at startup instead. | The declaring method |

All errors are ordinary PHPStan errors: baselines, `ignoreErrors`, and editor
integration work as usual.

## How it works

PHPStan analyses file-by-file, so whole-program reachability uses the
collector pattern: per-file collectors record call edges, declarations
(with attribute data) and class hierarchy facts; a single rule then runs once
on the merged data, builds the call graph, expands dynamic dispatch, computes
a reachability fixpoint and checks every contract. The result cache works:
adding `#[Effect]` to a leaf re-reports at distant sinks on the next run
without a full re-analysis (covered by an end-to-end test).

Because the analysis is closed-world, results are only correct when the whole
project is analysed. Partial analysis (e.g. single-file editor runs) reports
nothing rather than under-reporting silently.

## Known limitations (v1)

Not tracked — these are silent false negatives, by design:

- Variable and string callables (`$fn()`, `call_user_func('foo')`), container
  dispatch (`$container->get(X::class)->run()`).
- Reflection-based invocation.
- Code in the global scope (outside any function/method).
- Property hooks (PHP 8.4).
- Attribute arguments that are not compile-time constant strings (class
  constants work; runtime expressions are skipped).

Conditional effects ("slow only on cold cache") have no path-sensitivity —
model the wrapper with `#[HandlesEffect]` instead.

## License

MIT
