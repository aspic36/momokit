# Sandbox public MomoKit

API mobile money simulée pour tester un parcours de paiement complet sans compte, sans credentials et sans argent réel.

- API JSON : création de clé, paiement, statut, simulation
- Page de paiement « opérateur » pour simuler le client
- Webhooks signés (HMAC SHA-256, format `t=<ts>,s=<sig>`)
- SQLite ; les paiements de plus de 7 jours sont purgés

## Lancer en local

```bash
cd sandbox
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

Ouvrir http://127.0.0.1:8080 : la page affiche une clé sandbox et des exemples `curl`.

Variables d'environnement :

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `APP_URL` | déduite de la requête | URL publique utilisée dans `checkout_url` |
| `DB_PATH` | `sandbox/storage/sandbox.sqlite` | fichier SQLite |
| `SANDBOX_ALLOW_PRIVATE_WEBHOOKS` | `0` | `1` autorise les callbacks locaux (tests) |

## Déploiement

Le dépôt fournit un blueprint Render (`render.yaml`) et une image Docker construite depuis la racine :

```bash
docker build -f sandbox/Dockerfile -t momokit-sandbox .
docker run --rm -p 8080:8080 momokit-sandbox
```

Sur Render : New → Blueprint → sélectionner le dépôt. Le disque est éphémère sur l'offre gratuite : les données repartent de zéro à chaque redéploiement.

## API

Voir [docs/sandbox.md](../docs/sandbox.md) pour la référence complète.
