# Audit qualité — sylius-facturx-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Revue qualitative par l'orchestrateur (lecture directe des sources) · Outillage (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`.

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check --no-progress-bar` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse --no-progress` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit --no-coverage` | ✅ 109 tests, 1 750 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 25 fichiers avec suggestions |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 packages abandonnés (pas de CVE) |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 25 fichiers signalés relèvent de deux règles : `LocallyCalledStaticMethodToNonStaticRector` (méthodes `private static` dans les classes `final`) et `FlipTypeControlToUseExclusiveTypeRector` (conditions `null !== $x` retournables). Ce sont des **suggestions de modernisation non bloquantes**, non appliquées. Lancer `make rector-fix` pour les appliquer puis relancer ECS/PHPStan/tests.

**`composer audit`** signale deux packages abandonnés transitifs (`behat/transliterator` → aucun remplaçant officiel ; `php-http/message-factory` → migrer vers `psr/http-factory`). Aucune advisory de sécurité connue. À surveiller lors des prochaines mises à jour de dépendances.

**`ecs.php`** : déjà aligné sur le standard — importe `vendor/sylius-labs/coding-standard/ecs.php`, paths `src` + les trois répertoires tests existants + `ecs.php` lui-même. Aucune modification nécessaire.

## 2. Audit qualitatif (revue par l'orchestrateur)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | Typage strict partout, `final` sur tous les services, interfaces complètes, modèles immuables, décorateur propre. |
| Intégration Symfony / Sylius | **8/10** | Décorateur câblé dans un seul point (`config/services.php`), formulaires standards, compat 2.2/2.3 proprement gérée. |
| Sécurité (OWASP 2025) | **7/10** | Pas d'injection XML ni XXE, identifiants normalisés, filename sanitisé. Risques résiduels : autorisation fine sur le téléchargement XML, messages d'exceptions dans les flash admin, IBAN en clair, packages abandonnés. |

> Caveats : la suite non-unit (functional/integration) n'a pas été exécutée ; la conformité EN 16931 repose entièrement sur `FacturXAssertionsTrait` (XSD + règles métier PEPPOL), non relue en détail ici.

### Points forts

- `declare(strict_types=1)` + classes `final` sur l'ensemble des services et modèles de `src/`.
- Modèles immuables (`FacturXInvoice`, `InvoiceLine`, `VatBreakdown`, `Party`, `Address`, …) : pas d'état mutable après construction.
- `FacturXInvoicePdfFileGenerator` : décorateur résilient — toute erreur Factur-X est loguée et le PDF original est conservé, l'invoice n'est jamais bloquée.
- `FacturXInvoiceMapper` : réconciliation des montants cent-par-cent avant écriture XML (`amountsDoNotReconcile`) — garantit l'intégrité comptable du document légal.
- XML via horstoeko builder : aucune concaténation de chaînes dans le XML, escaping délégué à la bibliothèque, pas de surface d'injection.
- `ChannelSettings` : normalisation systématique (`.normalizeIdentifier()`, `.normalize()`) à chaque setter — les identifiants légaux sont stockés dans un format canonique.
- `FacturXController::xmlFilename()` : sanitisation du nom de fichier avec whitelist `[A-Za-z0-9_-]`.
- Couverture d'assertions élevée : 1 750 assertions pour 109 tests unitaires.

## 3. Recommandations priorisées

### 🟠 Majeur

1. **Absence de vérification d'autorisation fine dans `downloadXmlAction`** (`src/Controller/Admin/FacturXController.php:121–143`, OWASP A01:2025). Tout utilisateur avec `ROLE_ADMINISTRATION_ACCESS` peut télécharger le `factur-x.xml` de n'importe quelle facture en connaissant son UUID. Si l'admin est multi-canaux avec des droits restreints par canal, la vérification n'est pas présente au niveau applicatif. → Ajouter un contrôle `$invoice->channel()` vs les canaux autorisés de l'utilisateur courant, ou déléguer à `isGranted('SYLIUS_INVOICE_VIEW', $invoice)` si l'ACL Sylius le supporte.

2. **SRP du contrôleur** (`src/Controller/Admin/FacturXController.php`, 9 dépendances, 3 responsabilités distinctes). `FacturXController` gère les paramètres Factur-X du canal, les identifiants B2B client, et le téléchargement XML — trois concerns non liés. → Extraire `ChannelSettingsController`, `CustomerIdentifiersController` et `InvoiceXmlController`. Ramène chaque classe à 3–4 dépendances.

3. **`ChannelSettings` non `final`** (`src/Entity/ChannelSettings.php:9`). Toutes les autres classes de service sont `final`. L'entité `ChannelSettings` est une mapped superclass extensible (convention Sylius), ce qui est intentionnel, mais cela contraste avec le reste du style. Si l'extension via mapped superclass est requise, documenter explicitement ce choix dans le docblock. Si l'extensibilité n'est pas un objectif documenté, passer en `final`.

4. **Packages abandonnés dans les dépendances transitives** (`composer audit`, OWASP #6 Supply Chain 2025) : `behat/transliterator` (aucun remplaçant) et `php-http/message-factory` (migrer vers `psr/http-factory`). Pas de CVE actuel, mais risque supply chain à horizon 1 an. → Forcer la migration dans `sylius/invoicing-plugin` ou noter dans le `README` comme contrainte dépendante.

### 🟡 Mineur

- **Messages d'exceptions exposés dans les flash admin** (`src/Controller/Admin/FacturXController.php:131`) : `$exception->getMessage()` est rendu dans un flash Twig et peut contenir des détails de configuration (noms de channel, raisons techniques). Acceptable dans la route admin, mais à surveiller si les messages horstoeko incluent des chemins système. → Logguer le message complet et afficher un message générique côté flash.

- **IBAN et BIC en clair en base** (`src/Entity/ChannelSettings.php:37–38`) : données bancaires stockées sans chiffrement. Requis légalement pour la facturation (données imprimées sur le PDF), mais l'absence de chiffrement au repos est un écart aux recommandations OWASP A02:2025 pour les données financières. → Évaluer le chiffrement au repos via une colonne chiffrée Doctrine (ex. `defuse/php-encryption`) ou accepter le risque documenté.

- **`php -d memory_limit=2G` absent des cibles Makefile existantes** (`Makefile:57–58`, cible `phpstan`) : la cible `phpstan` du Makefile lance PHPStan sans le flag mémoire, contrairement aux instructions de `CLAUDE.md`. → Aligner la cible Makefile.

- **Rector : 25 fichiers** — appliquer `make rector-fix`, puis relancer ECS/PHPStan/tests. Les règles `LocallyCalledStaticMethodToNonStaticRector` et `FlipTypeControlToUseExclusiveTypeRector` sont non bloquantes mais améliorent la lisibilité.

- **`RegenerateInvoicePdfsCommand` sans pagination** (`src/Command/RegenerateInvoicePdfsCommand.php:67`) : `findBy()` sans `setMaxResults()` charge toutes les factures en mémoire. Sur un volume important, risque d'out-of-memory en production. → Traiter par batches (ex. 100 factures par itération).

### ℹ️ Pour information (choix de conception assumés)

- Fallback "plain PDF" silencieux (`FacturXInvoicePdfFileGenerator.php:41–48`) : décision documentée dans le code — une facture ne doit jamais échouer à cause de Factur-X. Le log `error` est présent ; un monitoring de ce log est recommandé en production.
- `Configuration.php` exclu de PHPStan (`phpstan.neon:12`) : contournement d'un crash PHPStan sur `processConfiguration()`, documenté.
- Union type `InvoiceFileManagerInterface|PdfFileManagerInterface` dans la commande : compatibilité Sylius 2.2/2.3 assumée, à supprimer quand la cible minimale sera 2.3.

## 4. Verdict

Plugin **solide et bien conçu** : outillage au vert (ECS, PHPStan level max, 109 tests unitaires / 1 750 assertions), typage strict, décorateur résilient, intégrité comptable vérifiée cent-par-cent avant écriture XML. Les axes d'amélioration prioritaires sont la **séparation du contrôleur** (3 responsabilités → 3 classes) et l'**autorisation fine** sur le téléchargement XML admin — à traiter avant une release production sur un Sylius multi-opérateurs. La commande de régénération nécessite une pagination avant un usage sur un catalogue de factures large. Rien de bloquant côté outillage, deux packages abandonnés transitifs à surveiller.
