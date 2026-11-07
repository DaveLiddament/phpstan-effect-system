<?php

declare(strict_types=1);

namespace EffectTest\Inference;

use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use EffectTest\StubVendor\Mailer;

// No attribute below declares an effect: every effect is inferred, from the
// ConventionInferrer or the equivalent `sources` configuration.

interface Repository
{
}

class OrderRepository implements Repository
{
    public function updateStatus(): void
    {
    }

    public function find(): void
    {
    }
}

// Not a Repository, so its update*() method has no effect.
class Counter
{
    public function updateTotal(): void
    {
    }
}

function deleteAll(): void
{
}

class OrderService
{
    #[EffectFree('state')]
    public function ship(OrderRepository $orders): void
    {
        $orders->updateStatus();
    }

    #[EffectFree('state')]
    #[EffectFree('io')]
    public function show(OrderRepository $orders, Counter $counter): void
    {
        $orders->find();
        $counter->updateTotal();
        file_get_contents('/tmp/report');
    }

    #[EffectFree('io')]
    public function export(): void
    {
        file_put_contents('/tmp/report', 'report');
    }

    #[EffectFree('io')]
    public function reset(): void
    {
        deleteAll();
    }

    // Mailer is unanalysed vendor code: 'smtp' is inferred, 'slow' is stubbed.
    #[EffectFree('smtp')]
    public function notify(Mailer $mailer): void
    {
        $mailer->send();
    }

    #[EffectFree('slow')]
    public function notifyLater(Mailer $mailer): void
    {
        $mailer->send();
    }
}

class AuditRepository implements Repository
{
    #[EffectFree('state')]
    public function updateLog(): void
    {
    }
}
