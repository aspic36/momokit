# MomoKit

**Une seule API PHP/Laravel pour les agrégateurs mobile money d'Afrique de l'Ouest — avec un mode test intégré.**

FedaPay, KkiaPay, PayDunya, CinetPay : chaque agrégateur a sa propre API, ses webhooks, ses codes d'erreur. MomoKit les rend interchangeables. Vous écrivez votre code de paiement une seule fois ; changer de fournisseur, c'est changer une ligne de configuration.

> **État : pré-version 0.1 — en développement.** Drivers `mock` et `fedapay` (collecte, statut, webhooks signés) + bridge Laravel. L'installation Packagist arrive avec la v0.1.

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

## Structure du dépôt

```
packages/core     SDK PHP pur (aucune dépendance framework)
packages/laravel  Bridge Laravel (façade, config, événements)
sandbox           Sandbox public gratuit (à venir — phase 3)
docs              Documentation FR/EN
```

## Feuille de route

1. `core` + driver `mock` + tests — **fait**
2. Driver FedaPay + webhooks signés — **fait** (à valider contre la vraie API sandbox)
3. `MobileMoney::fake()` + commande `mobile-money:simulate`
4. v0.1 sur Packagist + sandbox public en ligne
5. KkiaPay, PayDunya, CinetPay

## Licence

MIT. Les agrégateurs restent indispensables : MomoKit unifie leur usage, il ne les remplace pas.
