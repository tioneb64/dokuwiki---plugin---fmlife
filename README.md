# Fmlife : cycle de vie des fiches

Plugin DokuWiki (`fmlife`).

Statuts des fiches (🌱 brouillon, 🔎 à valider, ✅ validée, expirée, obsolète…), dates de création, de revue et d'expiration, et **rappels** pour les fiches à revoir ou expirées.

## Fonctions

- Page `?do=fmlife` : tableau de bord (fiches expirées, à revoir, à valider, brouillons).
- Balise `<fmreminders>` : bannière de rappel dans une page (par exemple l'accueil).
- Rappel quotidien par e-mail : `php bin/plugin.php fmlife remind --mail`.

## Configuration

| Réglage | Défaut | Rôle |
| --- | --- | --- |
| `review_months` | 12 | délai avant revue d'une fiche |
| `warn_days` | 30 | préavis avant expiration |
| `validity_months` | 12 | durée de validité par défaut : une fiche sans `expire` expire N mois après sa dernière revue, à défaut sa dernière modification, à défaut sa création (0 = pas d'expiration calculée) |
| `mailto` | | adresse du rappel par e-mail |
| `exclude_ns` | `10_pilotage:tasknotes` | espaces de noms ignorés |

## Dépendances

`fmeta`.

## Installation

Copier le contenu de ce dépôt dans `lib/plugins/fmlife/` (le fichier `plugin.info.txt` doit se trouver à la racine de ce dossier), puis vider le cache en rendant la date de `conf/local.php` plus récente (`touch conf/local.php`).

Avec le gestionnaire d'extensions de DokuWiki : *Administration → Gestionnaire d'extensions → Installer*, en indiquant l'adresse du dépôt (archive ZIP).

## Licence

Aucune licence n'a encore été choisie : à ajouter (fichier `LICENSE`) avant de publier le dépôt. DokuWiki est sous GPL-2.0.
