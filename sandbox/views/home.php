<?php

declare(strict_types=1);

use function MomoKit\Sandbox\e;

/** @var string $key */
/** @var string $webhookSecret */
/** @var string $baseUrl */
/** @var list<array<string, mixed>> $payments */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MomoKit Sandbox</title>
    <style>
        :root {
            color-scheme: light dark;
            --accent: #0b8f6b;
            --muted: #6b7280;
            --line: color-mix(in srgb, currentColor 14%, transparent);
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 3rem 1.25rem 4rem; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.55; }
        main { max-width: 880px; margin: 0 auto; }
        h1 { margin: 0; font-size: clamp(2rem, 6vw, 3rem); letter-spacing: -0.03em; }
        h1 span { color: var(--accent); }
        h2 { margin: 0 0 .75rem; font-size: 1rem; letter-spacing: .08em; text-transform: uppercase; }
        p { margin: .5rem 0; }
        .lead { max-width: 55ch; color: var(--muted); font-size: 1.1rem; }
        .card { margin-top: 1.25rem; padding: 1.25rem; border: 1px solid var(--line); border-radius: 14px; }
        pre { margin: .5rem 0 0; padding: .9rem 1rem; overflow-x: auto; border-radius: 10px; background: color-mix(in srgb, currentColor 7%, transparent); font-size: .85rem; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .muted { color: var(--muted); font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: .5rem .6rem; border-bottom: 1px solid var(--line); text-align: left; }
        th { color: var(--muted); font-weight: 600; }
        .badge { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-succeeded { background: #d1fae5; color: #065f46; }
        .badge-failed, .badge-cancelled { background: #fee2e2; color: #991b1b; }
        footer { margin-top: 2rem; color: var(--muted); font-size: .85rem; }
        a { color: var(--accent); }
        @media (prefers-color-scheme: dark) {
            .badge-pending { background: #78350f; color: #fde68a; }
            .badge-succeeded { background: #064e3b; color: #a7f3d0; }
            .badge-failed, .badge-cancelled { background: #7f1d1d; color: #fecaca; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <h1>MomoKit <span>Sandbox</span></h1>
        <p class="lead">Un agrégateur mobile money réaliste pour tester votre parcours de paiement : sans compte, sans credentials, sans argent réel.</p>
    </header>

    <section class="card">
        <h2>Votre clé sandbox</h2>
        <pre><code><?= e($key) ?></code></pre>
        <p class="muted">Secret webhook : <code><?= e($webhookSecret) ?></code></p>
        <p class="muted">Rechargez <a href="/?reset=1">une nouvelle clé</a> pour repartir de zéro. Les paiements de plus de 7 jours sont purgés.</p>
    </section>

    <section class="card">
        <h2>1. Créer un paiement</h2>
        <pre><code>curl -X POST <?= e($baseUrl) ?>/api/v1/payments \
  -H "Authorization: Bearer <?= e($key) ?>" \
  -H "Content-Type: application/json" \
  -d '{"amount":5000,"currency":"XOF","phone":"+22997000001","description":"Commande #42","callback_url":"https://webhook.site/votre-url"}'</code></pre>
        <p class="muted">La réponse contient <code>id</code>, <code>status: "pending"</code> et <code>checkout_url</code>.</p>
    </section>

    <section class="card">
        <h2>2. Simuler le paiement du client</h2>
        <pre><code>curl -X POST <?= e($baseUrl) ?>/api/v1/payments/PAYMENT_ID/simulate \
  -H "Authorization: Bearer <?= e($key) ?>" \
  -H "Content-Type: application/json" \
  -d '{"status":"succeeded"}'</code></pre>
        <p class="muted">Statuts possibles : <code>succeeded</code>, <code>failed</code>, <code>cancelled</code>. Si un <code>callback_url</code> est fourni, un webhook signé est envoyé.</p>
    </section>

    <section class="card">
        <h2>3. Vérifier un webhook</h2>
        <pre><code>X-Momokit-Signature: t=1730000000,s=HMAC_SHA256("1730000000." + corps_brut, SECRET_WEBHOOK)</code></pre>
        <p class="muted">Comparez la signature avec le secret webhook affiché plus haut. Les paiements restent consultables via <code>GET /api/v1/payments/PAYMENT_ID</code>.</p>
    </section>

    <?php if ($payments !== []): ?>
        <section class="card">
            <h2>Vos derniers paiements</h2>
            <table>
                <thead><tr><th>ID</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><code><?= e($payment['id']) ?></code></td>
                        <td><?= e($payment['amount']) ?> <?= e($payment['currency']) ?></td>
                        <td><span class="badge badge-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span></td>
                        <td><a href="/pay/<?= e($payment['id']) ?>">page de paiement</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <footer>
        <p>MomoKit Sandbox — données de démonstration, aucun paiement réel. <a href="https://github.com/aspic36/momokit">Code et documentation</a>.</p>
    </footer>
</main>
</body>
</html>
