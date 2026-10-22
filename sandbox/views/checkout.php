<?php

declare(strict_types=1);

use function MomoKit\Sandbox\e;

/** @var array<string, mixed> $payment */
/** @var string|null $error */
$error ??= null;
$pending = $payment['status'] === 'pending';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Paiement <?= e($payment['id']) ?> — MomoKit Sandbox</title>
    <style>
        :root {
            color-scheme: light dark;
            --accent: #0b8f6b;
            --muted: #6b7280;
            --line: color-mix(in srgb, currentColor 14%, transparent);
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 3rem 1.25rem 4rem; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.55; }
        main { max-width: 560px; margin: 0 auto; }
        h1 { margin: 0 0 .25rem; font-size: 1.6rem; }
        .provider { color: var(--muted); font-size: .9rem; margin-bottom: 1.5rem; }
        .card { padding: 1.5rem; border: 1px solid var(--line); border-radius: 16px; }
        .amount { margin: 0 0 1rem; font-size: 2.4rem; font-weight: 700; letter-spacing: -0.03em; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: .35rem .9rem; margin: 1.25rem 0; font-size: .95rem; }
        dt { color: var(--muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.25rem; }
        button { flex: 1 1 140px; padding: .75rem 1rem; border: 1px solid var(--line); border-radius: 10px; background: transparent; color: inherit; font: inherit; font-weight: 600; cursor: pointer; }
        button.primary { border-color: var(--accent); background: var(--accent); color: #fff; }
        .badge { display: inline-block; padding: .1rem .6rem; border-radius: 999px; font-size: .8rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-succeeded { background: #d1fae5; color: #065f46; }
        .badge-failed, .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .error { padding: .7rem .9rem; margin-bottom: 1rem; border-radius: 10px; background: #fee2e2; color: #991b1b; font-size: .9rem; }
        .note { margin-top: 1.5rem; color: var(--muted); font-size: .85rem; }
        pre { margin: 1rem 0 0; padding: .8rem 1rem; overflow-x: auto; border-radius: 10px; background: color-mix(in srgb, currentColor 7%, transparent); font-size: .8rem; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        a { color: var(--accent); }
        @media (prefers-color-scheme: dark) {
            .badge-pending { background: #78350f; color: #fde68a; }
            .badge-succeeded { background: #064e3b; color: #a7f3d0; }
            .badge-failed, .badge-cancelled { background: #7f1d1d; color: #fecaca; }
            .error { background: #7f1d1d; color: #fecaca; }
        }
    </style>
</head>
<body>
<main>
    <h1>Paiement mobile money</h1>
    <p class="provider">MomoKit Sandbox — page de démonstration</p>

    <div class="card">
        <?php if ($error !== null): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <p class="amount"><?= e(number_format((int) $payment['amount'], 0, ',', ' ')) ?> <?= e($payment['currency']) ?></p>
        <span class="badge badge-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span>

        <dl>
            <dt>Identifiant</dt>
            <dd><code><?= e($payment['id']) ?></code></dd>
            <dt>Téléphone</dt>
            <dd><?= e($payment['phone']) ?></dd>
            <?php if ($payment['description'] !== null): ?>
                <dt>Description</dt>
                <dd><?= e($payment['description']) ?></dd>
            <?php endif; ?>
            <?php if ($payment['callback_url'] !== null): ?>
                <dt>Webhook</dt>
                <dd><?= e($payment['callback_url']) ?></dd>
            <?php endif; ?>
        </dl>

        <?php if ($pending): ?>
            <form method="post" action="/pay/<?= e($payment['id']) ?>" class="actions">
                <button class="primary" name="status" value="succeeded">Payer</button>
                <button name="status" value="failed">Échec</button>
                <button name="status" value="cancelled">Annuler</button>
            </form>
        <?php else: ?>
            <p class="note">
                <?php if ($payment['webhook']['status'] !== null): ?>
                    Webhook envoyé — réponse HTTP <?= e($payment['webhook']['status']) ?>.
                <?php elseif ($payment['webhook']['error'] !== null): ?>
                    Webhook non délivré — <?= e($payment['webhook']['error']) ?>
                <?php else: ?>
                    Aucun <code>callback_url</code> configuré pour ce paiement.
                <?php endif; ?>
            </p>
            <pre><code><?= e(json_encode($payment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
        <?php endif; ?>

        <p class="note">
            <a href="/">Retour au sandbox</a> ·
            <a href="<?= e($payment['checkout_url']) ?>">Recharger cette page</a>
        </p>
    </div>

    <p class="note">Aucun argent réel n'est débité. Cette page simule l'interface de l'opérateur.</p>
</main>
</body>
</html>
