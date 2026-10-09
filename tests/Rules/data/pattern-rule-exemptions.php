<?php

declare(strict_types=1);

namespace EffectTest\PatternRuleExemptions\Controller {

    use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
    use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
    use DaveLiddament\PhpstanEffectSystem\Attributes\ExemptFromEffectRule;

    class Db
    {
        #[Effect('slow')]
        public function query(): void
        {
        }

        // No effects rule covers Db, so there is nothing to exempt from.
        #[ExemptFromEffectRule('slow', reason: 'Stale')]
        public function count(): void
        {
            $this->query();
        }
    }

    // Still covered by the rule.
    class HomeController
    {
        public function index(): void
        {
            (new Db())->query();
        }
    }

    // Excluded by class name.
    class DebugController
    {
        public function dump(): void
        {
            (new Db())->query();
        }
    }

    class ReportController
    {
        #[ExemptFromEffectRule('slow', reason: 'Only requested by the PDF renderer running in a queue job')]
        public function render(): void
        {
            (new Db())->query();
        }

        // Same class as render(), but not exempt.
        public function download(): void
        {
            (new Db())->query();
        }

        // Nothing slow is reached, so the exemption is stale.
        #[ExemptFromEffectRule('slow', reason: 'Stale')]
        public function preview(): void
        {
        }

        // An exemption cannot drop the method's own contract.
        #[EffectFree('slow')]
        #[ExemptFromEffectRule('slow', reason: 'Not allowed')]
        public function archive(): void
        {
        }

        #[ExemptFromEffectRule('slwo', reason: 'Typo')]
        public function summary(): void
        {
        }
    }

    // The exemption does not stop the effect propagating to callers.
    class DashboardController
    {
        public function show(): void
        {
            (new ReportController())->render();
        }
    }

    // A source that is exempt: no contradiction with the rule.
    class ExportController
    {
        #[Effect('slow')]
        #[ExemptFromEffectRule('slow', reason: 'Export is a background job')]
        public function export(): void
        {
        }
    }

    interface Job
    {
        #[EffectFree('slow')]
        public function handle(): void;
    }

    // An exemption cannot drop an inherited contract either.
    class ImportController implements Job
    {
        #[ExemptFromEffectRule('slow', reason: 'Not allowed')]
        public function handle(): void
        {
            (new Db())->query();
        }
    }
}

namespace EffectTest\PatternRuleExemptions\Controller\Internal {

    use EffectTest\PatternRuleExemptions\Controller\Db;

    // Excluded by namespace wildcard.
    class AdminController
    {
        public function run(): void
        {
            (new Db())->query();
        }
    }
}
