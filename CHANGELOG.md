# Journal des versions

## 0.1.0 — 2026-10-22

Première version publiée.

- `momokit/core` : contrat `Driver`, DTO `CollectRequest`/`Payment`/`WebhookEvent`, numéros E.164, driver `mock` (numéros magiques), driver FedaPay (collecte + lien de paiement, statut, webhooks signés), fake de test.
- `momokit/laravel` : service provider, façade `MobileMoney`, configuration publiable, `MobileMoney::fake()`, commande `mobile-money:simulate`.
- Sandbox public : API de paiement simulée, page de paiement, webhooks signés.
