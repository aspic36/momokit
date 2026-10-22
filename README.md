# MomoKit

**Une seule API PHP/Laravel pour les agrégateurs mobile money d'Afrique de l'Ouest — avec un mode test intégré.**

FedaPay, KkiaPay, PayDunya, CinetPay : chaque agrégateur a sa propre API, ses webhooks, ses codes d'erreur. MomoKit les rend interchangeables. Vous écrivez votre code de paiement une seule fois ; changer de fournisseur, c'est changer une ligne de configuration.

> **État : v0.1.0, prête à publier.** Drivers `mock` et `fedapay` (collecte, statut, webhooks signés), bridge Laravel, fake de test (`MobileMoney::fake()`), commande `mobile-money:simulate` et sandbox local. Les paquets sont prêts sur les dépôts de split ; la soumission Packagist attend la création du compte. Le sandbox est prêt pour Render (`render.yaml`).

## Pourquoi

- **Une interface unique** — `collect` aujourd'hui, `disburse` et `status` ensuite, identiques quel que soit l'agrégateur.
- **Mode test complet** — des numéros magiques déclenchent succès, échec, timeout ou annulation. Aucun compte marchand requis pour développer.
- **Pas de verrouillage** — passer de `fedapay` à `kkiapay` ne touche ni votre code métier, ni vos webhooks.
- **Docs FR/EN** — écrites en français d'abord.

## Démarrage rapide (Laravel)

```bash
composer require momokit/laravel
php artisan vendor:publish --tag=mobile-money-config
```

```php
use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Laravel\Facades\MobileMoney;

$payment = MobileMoney::collect(
    CollectRequest::make(5000, 'XOF', '+229 97 00 00 01', 'Commande #42'),
);

$payment->succeeded(); // true
```

Sans Laravel, le SDK s'utilise seul :

```php
use MomoKit\Core\Drivers\Mock\MockDriver;
use MomoKit\Core\MobileMoneyManager;

$mobileMoney = (new MobileMoneyManager())->register(new MockDriver());

$payment = $mobileMoney->collect(
    CollectRequest::make(5000, 'XOF', '+229 97 00 00 01'),
);
```

## Numéros de test (driver `mock`)

Les scénarios sont déclenchés par la fin du numéro, quel que soit l'indicatif :

| Fin du numéro | Scénario                                |
| ------------- | --------------------------------------- |
| `97 00 00 01` | Succès immédiat                          |
| `97 00 00 02` | Échec : solde insuffisant                |
| `97 00 00 03` | Timeout (le fournisseur ne répond pas)   |
| `97 00 00 04` | Annulation par le client                 |
| tout autre    | Succès                                   |

## FedaPay

```bash
# .env
FEDAPAY_SECRET_KEY=sk_sandbox_...
FEDAPAY_WEBHOOK_SECRET=wh_sandbox_...
FEDAPAY_ENVIRONMENT=sandbox
```

```php
$payment = MobileMoney::collect(
    CollectRequest::make(5000, 'XOF', '+229 97 00 00 01', 'Commande #42'),
    driver: 'fedapay',
);

// Rediriger le client vers la page de paiement FedaPay :
$payment->checkoutUrl;
```

Vérification d'un webhook (contrôleur Laravel) :

```php
use MomoKit\Core\Contracts\SupportsWebhooks;
use MomoKit\Core\Enums\WebhookEventType;
use MomoKit\Laravel\Facades\MobileMoney;

public function __invoke(Request $request, string $driver)
{
    $gateway = MobileMoney::driver($driver);

    if (! $gateway instanceof SupportsWebhooks) {
        abort(404);
    }

    $event = $gateway->parseWebhook(
        $request->getContent(),
        $request->header('X-FEDAPAY-SIGNATURE', ''),
    );

    if ($event->type === WebhookEventType::PaymentSucceeded) {
        // Marquer la commande comme payée...
    }

    return response()->json(['received' => true]);
}
```

La signature est vérifiée (HMAC SHA-256, tolérance de 5 minutes contre le rejeu) et l'événement est normalisé : votre code ne dépend pas du format de FedaPay.

## Tester sans réseau : `MobileMoney::fake()`

`MobileMoney::fake()` remplace tous les drivers par un simulateur en mémoire : collectes et vérifications de statut sont enregistrées, aucun appel réseau. Les paiements restent `pending` jusqu'à ce que le test décide de leur sort.

```php
use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Core\Enums\PaymentStatus;
use MomoKit\Laravel\Facades\MobileMoney;

public function test_checkout(): void
{
    $fake = MobileMoney::fake('fedapay');

    $payment = MobileMoney::collect(
        CollectRequest::make(5000, 'XOF', '+22997000001'),
        driver: 'fedapay',
    );

    $fake->assertCollected(fn (CollectRequest $request) => $request->amount === 5000);

    $fake->succeed($payment->id);

    $this->assertSame(
        PaymentStatus::Succeeded,
        MobileMoney::driver('fedapay')->status($payment->id)->status,
    );
}
```

Assertions disponibles : `assertCollected()`, `assertCollectedTimes()`, `assertNothingCollected()`, `assertStatusChecked()`. Contrôle du statut : `succeed()`, `fail()`, `cancel()` ou `markAs()`.

Simulateur en ligne de commande (démo, formation, test manuel) :

```bash
php artisan mobile-money:simulate fedapay --amount=5000 --status=succeeded
```

## Sandbox public

`sandbox/` contient une API mobile money simulée (clés sandbox, paiements, page de paiement, webhooks signés) pour tester un parcours complet sans compte :

```bash
cd sandbox
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

Ouvrir http://127.0.0.1:8080 : la page affiche une clé et des exemples `curl`. Déploiement prêt pour Render (`render.yaml`, Docker). Référence API : [docs/sandbox.md](docs/sandbox.md).

## Structure du dépôt

```
packages/core     SDK PHP pur (aucune dépendance framework)
packages/laravel  Bridge Laravel (façade, config, commande, fake)
sandbox           Sandbox public (API + page de paiement, Docker/Render)
docs              Documentation FR/EN
```

## Feuille de route

1. `core` + driver `mock` + tests — **fait**
2. Driver FedaPay + webhooks signés — **fait** (à valider contre la vraie API sandbox)
3. `MobileMoney::fake()` + commande `mobile-money:simulate` — **fait**
4. v0.1 sur Packagist + sandbox en ligne — **en attente** (compte Packagist à créer, déploiement Render)
5. KkiaPay, PayDunya, CinetPay

## Licence

MIT. Les agrégateurs restent indispensables : MomoKit unifie leur usage, il ne les remplace pas.
