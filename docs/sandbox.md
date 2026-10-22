# Sandbox API

Base URL : `https://<sandbox>/` (ou `http://127.0.0.1:8080` en local).

Toutes les réponses sont du JSON, sauf la page `/` et la page `/pay/{id}`.

## Authentification

Chaque appel API utilise une clé sandbox :

```
Authorization: Bearer sk_sandbox_...
```

La page `/` crée une clé automatiquement et l'affiche. Une clé peut aussi être créée par API :

```bash
curl -X POST https://<sandbox>/api/v1/keys
```

```json
{
  "key": "sk_sandbox_...",
  "webhook_secret": "whsec_...",
  "created_at": "2026-10-22T15:00:00+00:00"
}
```

## Créer un paiement

`POST /api/v1/payments`

```bash
curl -X POST https://<sandbox>/api/v1/payments \
  -H "Authorization: Bearer sk_sandbox_..." \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 5000,
    "currency": "XOF",
    "phone": "+22997000001",
    "description": "Commande #42",
    "callback_url": "https://webhook.site/votre-url"
  }'
```

| Champ | Requis | Détail |
| --- | --- | --- |
| `amount` | oui | entier positif, en plus petite unité |
| `currency` | non | défaut `XOF` |
| `phone` | oui | format international ou local |
| `description` | non | 200 caractères max |
| `callback_url` | non | URL publique http(s), webhook à chaque changement de statut |

Réponse `201` :

```json
{
  "id": "pay_a34ba7781d75db3058f2",
  "status": "pending",
  "amount": 5000,
  "currency": "XOF",
  "phone": "+22997000001",
  "description": "Commande #42",
  "callback_url": "https://webhook.site/votre-url",
  "checkout_url": "https://<sandbox>/pay/pay_a34ba7781d75db3058f2",
  "created_at": "2026-10-22T15:44:40+00:00",
  "updated_at": "2026-10-22T15:44:40+00:00",
  "webhook": {"status": null, "error": null}
}
```

## Consulter un paiement

`GET /api/v1/payments/{id}` — renvoie la même structure. Un paiement n'est visible qu'avec la clé qui l'a créé (`404` sinon).

## Simuler le paiement du client

`POST /api/v1/payments/{id}/simulate`

```bash
curl -X POST https://<sandbox>/api/v1/payments/pay_.../simulate \
  -H "Authorization: Bearer sk_sandbox_..." \
  -H "Content-Type: application/json" \
  -d '{"status":"succeeded"}'
```

Statuts : `succeeded`, `failed`, `cancelled`.

```json
{
  "payment": {"id": "pay_...", "status": "succeeded", "...": "..."},
  "webhook": {"delivered": true, "status": 200, "error": null}
}
```

`webhook` vaut `null` si aucun `callback_url` n'est configuré.

La page `GET /pay/{id}` fait la même chose depuis un navigateur, avec trois boutons (Payer, Échec, Annuler).

## Webhooks

À chaque simulation avec `callback_url`, le sandbox envoie :

```json
{
  "event": "payment.succeeded",
  "payment": {"id": "pay_...", "status": "succeeded", "...": "..."}
}
```

En-têtes :

```
Content-Type: application/json
User-Agent: MomoKit-Sandbox/0.1
X-Momokit-Signature: t=1792683880,s=36768d0f...
```

Vérification (PHP) :

```php
[$timestamp, $signature] = your_parse_signature($request->header('X-Momokit-Signature'));

$expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $webhookSecret);

if (! hash_equals($expected, $signature)) {
    abort(400);
}
```

Rejetez aussi les timestamps trop anciens (5 minutes suffisent) pour éviter le rejeu. Les URLs privées, loopback et non-http(s) sont refusées.

## Limites

- Données publiques de démonstration, purgées après 7 jours.
- Pas de vraie collecte : aucun débit, aucun opérateur contacté.
- Une seule région Render gratuite ; le service peut s'endormir et perdre ses données au redéploiement.
