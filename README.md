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
    #[EffectFree('slow')]             // the sink: must never reach 'slow' — ERROR reported here
    public function indexAction(): void
    {
        (new OrderService())->load();
    }
}
```

```
Method HomeController::indexAction() is #[EffectFree('slow')] but reaches effect 'slow':
HomeController::indexAction() -> OrderService::load() -> ApiClient::fetch() (declares #[Effect('slow')]).
```

Effect names are arbitrary strings — invent whatever taxonomy fits your
architecture (`slow`, `io`, `http`, `db`, `nondeterministic`, ...).

> **Stability: experimental.** This package is pre-1.0. The attribute API,
> configuration schema and error identifiers may change in any release.
> Error identifiers end up in baselines and `ignoreErrors` entries, so pin an
> exact version until 1.0.

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

The package requires `phpstan/phpstan`, so install it in `require-dev`. That
is safe even though the attributes appear in production code: the attribute
classes themselves have zero dependencies, and PHP never autoloads an attribute
class unless something reflects on it.

Requires PHP 8.4 or 8.5, and PHPStan 2.x. Each new PHP version is added once
it has been released and the test suite passes on it.

## The attributes

All three target methods and functions, and are repeatable.

| Attribute | Meaning |
|---|---|
| `#[Effect('slow')]` | This method **has** the effect (a source). |
| `#[EffectFree('slow')]` | This method must not transitively reach the effect (a sink). Valid on interface/abstract methods; the contract applies to every implementation and override, and cannot be dropped. |
| `#[HandlesEffect('slow')]` | This method **discharges** the effect: callees' `slow` effects do not propagate through it (e.g. a caching wrapper). An `#[Effect('slow')]` declared on the same method still counts. |

### Handling effects: a worked example

`#[HandlesEffect]` is for boundaries that genuinely contain an effect — the
classic case is a cache in front of something slow:

```php
use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use DaveLiddament\PhpstanEffectSystem\Attributes\HandlesEffect;

class ExchangeRateApi
{
    #[Effect('slow')]
    public function fetchRate(): float { /* HTTP call */ }
}

class CachedExchangeRates
{
    #[HandlesEffect('slow')]   // discharges 'slow': it stops here
    public function rate(): float
    {
        return $this->cache->get('rate', fn () => (new ExchangeRateApi())->fetchRate());
    }
}

class PriceCalculator
{
    #[EffectFree('slow')]
    public function total(int $qty): float
    {
        return $qty * (new CachedExchangeRates())->rate();   // OK — no violation
    }
}
```

Without the `#[HandlesEffect('slow')]`, `total()` would be reported: `slow`
would propagate from `fetchRate()` through `rate()` (the closure's calls count
as `rate()`'s calls). Note the handler only blocks effects arriving from
callees — if `rate()` itself also declared `#[Effect('slow')]` (say, a cold
cache is still slow enough to matter), that effect would still propagate.

## Semantics

- **Propagation** — effects flow from callee to caller, transitively, through
  the inferred call graph. Recursion and mutual recursion are handled.
- **HandlesEffect** — blocks propagation of that effect from callees. It does
  not cancel an `Effect` of the same name declared on the same method.
- **EffectFree inheritance** — a contract on an interface or parent method
  applies to every implementation/override; there is no syntax to drop it.
  Violations are reported at the implementation. This includes a method
  inherited from a parent that is unrelated to the interface (`class Child
  extends Base implements Runner`, with `run()` declared only in `Base`):
  the error is reported on `Base::run()`, naming `Child` as the link. Private
  methods never inherit a contract — a same-named method in a subclass is not
  an override. Constructors do **not** inherit either: `new Child()` always
  names the concrete class, so nobody constructs a `Child` relying on what
  `Base::__construct()` promised, and PHP itself exempts constructors from
  compatibility checks. The exception is a constructor declared on an
  interface or as `abstract` — PHP enforces that signature on every
  implementation, and the contract is inherited with it. To keep a whole
  hierarchy cheap to construct, use a pattern rule instead
  (`classPattern: 'App\Entity\*', methodPattern: '__construct'`). A
  `new static()` factory is still covered: the factory's own contract reaches
  subclass constructors through dispatch.
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
- **Late static binding** — `static::method()`, `new static()`,
  `$object::method()`, `$classString::method()` and `new $classString()` are
  expanded over all known subclasses, like any other dynamic dispatch.

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
project is analysed. When PHPStan is given individual files (e.g. single-file
editor runs, `phpstan analyse src/Foo.php`) the extension reports nothing
rather than under-reporting silently. It **cannot** detect a run restricted to
a sub-directory (`phpstan analyse src/Controller`): such a run only sees the
calls, implementations and effects inside that directory and will silently
miss violations. Always run it over the full configured `paths`.

Errors are reported on the first line of the sink's declaration, which is the
first attribute line when the method has attributes — that is where a
`@phpstan-ignore effects.violation` comment has to go (above the attributes,
not between them and the `function` keyword).

## Comparison with adjacent tools

- **[spaze/phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls)**
  forbids calls to specific functions/methods — but only **direct** calls at
  the call site. Use it for "never call `eval()` anywhere"; use this extension
  when the thing you're forbidding may be buried arbitrarily deep in the call
  graph.
- **[phpstan/phpstan-deprecation-rules](https://github.com/phpstan/phpstan-deprecation-rules)**
  is the same shape: a marker (`@deprecated`) enforced at direct usage sites,
  with no transitive propagation.
- **Psalm's [taint analysis](https://psalm.dev/docs/security_analysis/)** is
  the closest semantic relative — sources, sinks and sanitizers map directly
  onto `Effect`, `EffectFree` and `HandlesEffect`. The difference: taint
  analysis tracks the **flow of data values** for security (user input
  reaching SQL or HTML), while this extension tracks **call reachability** of
  arbitrary named behaviours. And it's Psalm, not PHPStan.
- **[deptrac](https://github.com/deptrac/deptrac)** and
  **[phpat](https://github.com/carlosas/phpat)** enforce architectural layer
  boundaries from static class dependencies. They answer "who may *reference*
  whom" (structural); this extension answers "what may a call *transitively
  do*" (behavioural). They compose well together.
- Outside PHP, the closest analogue is Google's
  [Capslock](https://github.com/google/capslock) for Go: transitive
  capability analysis over a whole-program call graph.

## Known limitations (v1)

Not tracked — these are silent false negatives, by design:

- Variable, string and array callables (`$fn()`, `call_user_func('foo')`,
  `array_map([$obj, 'method'], ...)`), container dispatch
  (`$container->get(X::class)->run()`).
- Calls on receivers PHPStan has no class type for (untyped properties and
  parameters, `mixed`, `object`). The better typed the code, the more complete
  the call graph.
- Implicit calls: `__invoke` (`$obj()`), `__toString`, `__get`/`__set`,
  `__call`/`__callStatic` (including `@method`-annotated magic methods),
  `__destruct`, `__clone`, `ArrayAccess`, iterators.
- `new` expressions in parameter default values.
- Vendor implementations of an interface: dispatch is only expanded over
  analysed classes, so a call through a vendor interface needs the stub on
  the interface method itself.
- Reflection-based invocation.
- Code in the global scope (outside any function/method).
- Property hooks (PHP 8.4).
- Attribute arguments that are not compile-time constant strings (class
  constants work; runtime expressions are skipped).

Conditional effects ("slow only on cold cache") have no path-sensitivity —
model the wrapper with `#[HandlesEffect]` instead.

## License

MIT
