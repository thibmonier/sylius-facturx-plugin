# Sylius Factur-X Plugin

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-facturx-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-facturx-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Factures électroniques Factur-X / ZUGFeRD (EN 16931) pour le Sylius 2 Invoicing Plugin.**

Le [Sylius Invoicing Plugin](https://github.com/Sylius/InvoicingPlugin) officiel génère des
factures PDF. Depuis septembre 2026, les entreprises françaises doivent être en mesure de
recevoir des factures électroniques, et les grandes et moyennes entreprises doivent en émettre
(les PME à partir de septembre 2027) ; les entreprises allemandes doivent les accepter depuis
2025. Un simple PDF n'est pas une facture électronique.

Ce plugin transforme les PDF de facture en **fichiers Factur-X PDF/A-3 avec un `factur-x.xml`
intégré** (UN/CEFACT Cross Industry Invoice, profil **EN 16931**), le format hybride accepté en
France et en Allemagne (où il s'appelle ZUGFeRD), gratuit et open source.

## Fonctionnalités

| Besoin | Ce que fait le plugin |
|---|---|
| Émettre des factures électroniques sans changer le flux de facturation | Le PDF généré par l'Invoicing Plugin devient un **PDF/A-3 avec `factur-x.xml`** et les métadonnées XMP Factur-X, avant d'être stocké : les liens de téléchargement existants de l'administration et de la boutique le servent. |
| Une facture lisible par une machine et dont les totaux concordent | XML au profil **EN 16931 (COMFORT)** : lignes avec prix nets et bruts, remises sur les articles, frais de livraison sous forme de ligne, ventilation de la TVA multi-taux, totaux qui concordent au centime près avec le PDF. |
| Transmettre le XML à un comptable ou à une plateforme | Bouton de téléchargement **XML Factur-X** sur la page de facture de l'administration, dans la grille des factures et dans les factures de la page de commande. |
| Données légales du vendeur | **Paramètres Factur-X par canal** : raison sociale, SIREN, SIRET, numéro de TVA, forme juridique et capital social, registre du commerce, adresse électronique, conditions et date d'échéance de paiement, IBAN / BIC, notes de paiement. Les SIREN, SIRET et numéros de TVA français sont contrôlés (Luhn, clé de TVA). |
| Mentions françaises 2026 | Catégorie d'opération et état du paiement dans le processus métier (BT-23 : `B1`, `S2`, `M1`...), SIREN du vendeur et de l'acheteur, adresse de livraison lorsqu'elle diffère, option de TVA sur les débits (BT-8), notes `PMT` / `PMD` / `AAB`. |
| Clients B2B | Carte facultative d'**identifiants B2B** sur la page client de l'administration : raison sociale, SIREN, numéro de TVA, adresse électronique. |
| Exonérations de TVA | Catégorie utilisée pour les lignes sans TVA (exonéré, taux zéro, autoliquidation, intracommunautaire, export, non soumis à la TVA) avec le motif d'exonération et le code VATEX. |
| Installer sur une boutique en production | `tmonier:facturx:regenerate-pdfs` génère à nouveau les PDF de facture stockés au format Factur-X. |
| Envoyer les factures à une *plateforme agréée* | Non inclus : l'événement `tmonier_facturx.invoice_generated` fournit le XML et le PDF à un connecteur. |

Également inclus : traductions en anglais, français, allemand, espagnol, italien, néerlandais et
polonais ; Twig Hooks partout ; prise en charge de MySQL, MariaDB et PostgreSQL.

## Captures d'écran

![Paramètres Factur-X d'un canal](docs/images/admin-channel-facturx-settings.png)

![Page de facture avec le téléchargement du XML Factur-X](docs/images/admin-invoice-facturx.png)

## Prérequis

| | Version |
|---|---|
| PHP | 8.2+ (ext-dom, ext-gd, ext-zip, ext-fileinfo) |
| Sylius | 2.2, 2.3 |
| Symfony | 6.4, 7.4, 8.0 |
| [sylius/invoicing-plugin](https://github.com/Sylius/InvoicingPlugin) | 2.2+, avec la génération de PDF activée |

Le PDF de l'Invoicing Plugin doit être lisible par l'analyseur libre FPDI (pas de flux de
références croisées compressés) : c'est le cas des PDF générés par wkhtmltopdf, le moteur de
rendu par défaut. Le plugin dépend de [horstoeko/zugferd](https://github.com/horstoeko/zugferd) (MIT),
qui nécessite lui-même `smalot/pdfparser` (LGPL-3.0, installé par Composer, non inclus).

## Installation

1. Installez et configurez d'abord le [Sylius Invoicing Plugin](https://github.com/Sylius/InvoicingPlugin#installation)
   (bundles, configuration, routes, migrations, wkhtmltopdf).

2. Ajoutez le paquet :

    ```bash
    composer require tmonier/sylius-facturx-plugin
    ```

3. Enregistrez le bundle dans `config/bundles.php`, après l'Invoicing Plugin :

    ```php
    Sylius\InvoicingPlugin\SyliusInvoicingPlugin::class => ['all' => true],
    Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin::class => ['all' => true],
    ```

4. Importez la configuration dans `config/packages/tmonier_sylius_facturx.yaml` :

    ```yaml
    imports:
        - { resource: "@TmonierSyliusFacturXPlugin/config/config.yaml" }
    ```

5. Importez les routes de l'administration dans `config/routes/tmonier_sylius_facturx.yaml` :

    ```yaml
    tmonier_sylius_facturx:
        resource: "@TmonierSyliusFacturXPlugin/config/routes.yaml"
    ```

6. Exécutez les migrations :

    ```bash
    bin/console doctrine:migrations:migrate
    ```

7. Dans l'administration, ouvrez **« Channels » → (modifier un canal) → « Paramètres Factur-X »**,
   activez Factur-X et renseignez les données du vendeur. L'adresse du vendeur provient des
   **données de facturation de la boutique** du canal (société, numéro fiscal, rue, code postal,
   ville, pays) : renseignez-les également.

8. Facultatif : générez à nouveau au format Factur-X les PDF des factures émises auparavant :

    ```bash
    bin/console tmonier:facturx:regenerate-pdfs --dry-run -v
    bin/console tmonier:facturx:regenerate-pdfs [--channel=WEB] [--invoice=2026/10/000001]
    ```

## Configuration

```yaml
# config/packages/tmonier_sylius_facturx.yaml
tmonier_sylius_facturx:
    enabled: true                         # global switch; each channel must be enabled too
    profile: en16931                      # the only supported profile
    attachment_relationship: Alternative  # AFRelationship of factur-x.xml: Alternative, Data or Source
```

`Alternative` est obligatoire en Allemagne pour le profil EN 16931 et accepté en France.

Les ressources `ChannelSettings` et `CustomerIdentifiers` peuvent être étendues comme n'importe
quelle ressource Sylius (`tmonier_sylius_facturx.resources.channel_settings.classes.model`, ...).

## Ce qui se retrouve dans le XML

| Donnée de la facture | Factur-X |
|---|---|
| Numéro de facture, date d'émission, devise, numéro de commande | BT-1, BT-2, BT-5, BT-14 |
| Vendeur : raison sociale, SIREN, SIRET, numéro de TVA, forme juridique + registre du commerce, adresse | BT-27, BT-30 (scheme 0002), BT-29 (0009), BT-31, BT-33, BG-5 |
| Acheteur : société ou nom de facturation, adresse, identifiants B2B | BT-44, BG-8, BT-47, BT-48, BT-49 |
| Adresse de livraison, lorsqu'elle diffère de l'adresse de facturation | BG-15 |
| Dernière date d'expédition de la commande (rien tant qu'aucun envoi n'est expédié) | BT-72 |
| Lignes : produit (ou mode de livraison), code de la variante, quantité, prix unitaire brut et net, montant net, catégorie et taux de TVA | BG-25 |
| Ventilation de la TVA par catégorie et par taux | BG-23 |
| Totaux, montant payé, montant dû, date d'échéance (paramètres du canal ou fournisseur de conditions de paiement) | BG-22, BT-9 |
| IBAN / BIC, conditions de paiement (paramètres du canal ou fournisseur de conditions de paiement), notes | BG-16, BT-20, BT-22 |

Les montants sont ceux de la facture : Sylius arrondit la TVA par unité, un montant de TVA peut
donc différer de quelques centimes de *base imposable × taux*, ce que l'EN 16931 autorise. La
correspondance complète, les règles françaises et les sources figurent dans [SPEC.md](SPEC.md).

## Point d'extension : conditions de paiement par commande

Par défaut, la date d'échéance (BT-9) est la date d'émission plus le *délai de paiement* du canal
lorsqu'un montant reste dû, et les conditions de paiement (BT-20) sont le texte des *conditions de
paiement* du canal. Un plugin qui connaît mieux la situation pour certaines commandes (par exemple
le crédit client : « 45 jours fin de mois », conditions propres à chaque client) les fournit au
moyen d'un `InvoicePaymentTermsProviderInterface` :

```php
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTerms;
use Tmonier\SyliusFacturXPlugin\PaymentTerms\InvoicePaymentTermsProviderInterface;

#[AsTaggedItem(priority: 10)]
final class TradeCreditPaymentTermsProvider implements InvoicePaymentTermsProviderInterface
{
    public function provide(InvoiceInterface $invoice, ChannelSettingsInterface $settings, int $duePayableAmount): ?InvoicePaymentTerms
    {
        $terms = $this->termsRepository->findOneByOrder($invoice->order());
        if (null === $terms) {
            // $duePayableAmount (cents) is 0 for a paid invoice: the due date is then dropped.
            return null; // next provider, then the channel settings
        }

        return new InvoicePaymentTerms($terms->getDueDate(), $terms->getLabel());
    }
}
```

Les services autoconfigurés reçoivent le tag `tmonier_facturx.invoice_payment_terms_provider`
(définissez `priority` sur le tag ou avec `#[AsTaggedItem]`, la plus élevée d'abord). Le premier
fournisseur qui renvoie des conditions l'emporte ; les paramètres du canal s'appliquent si aucun
n'en renvoie. Lorsqu'un montant est dû, les conditions sans date d'échéance ni texte sont
ignorées, de sorte que le XML satisfait toujours BR-CO-25. Les factures payées (rien à régler)
n'ont jamais de date d'échéance (BT-9), quoi que renvoie le fournisseur ; son texte est conservé.
L'agrégat est disponible sous le nom `InvoicePaymentTermsProviderInterface`
(`tmonier_facturx.payment_terms_provider`).

## Point d'extension : transmission à une plateforme

```php
use Tmonier\SyliusFacturXPlugin\Event\FacturXInvoiceGenerated;

#[AsEventListener(event: FacturXInvoiceGenerated::NAME)]
final class SendToMyPlatform
{
    public function __invoke(FacturXInvoiceGenerated $event): void
    {
        $event->getInvoice();      // Sylius\InvoicingPlugin\Entity\InvoiceInterface
        $event->getXml();          // factur-x.xml
        $event->getPdfContent();   // Factur-X PDF/A-3
        $event->getPdfFilename();  // file name in the "sylius_invoicing" storage
    }
}
```

L'événement est déclenché à chaque génération du PDF, avant son stockage, y compris lors d'une
nouvelle génération : les écouteurs doivent être idempotents (utilisez l'identifiant de la
facture). Un écouteur défaillant est consigné dans les logs et n'empêche pas la facture.

## Personnalisation

| Hook | Contenu |
|---|---|
| `sylius_admin.invoice.show.content.header.title_block.actions` → `tmonier_facturx_download_xml` | Bouton de téléchargement du XML de la page de facture |
| `sylius_admin.order.show.content.sections.invoices.table.body.actions` → `tmonier_facturx_download_xml` | Téléchargement du XML dans les factures de la page de commande |
| `sylius_admin.channel.update.content.header.title_block.actions` → `tmonier_facturx_settings` | Lien vers les paramètres Factur-X |
| `sylius_admin.customer.show.content.sections` → `tmonier_facturx_identifiers` | Carte des identifiants B2B |
| `tmonier_facturx.admin.channel.settings` / `tmonier_facturx.admin.customer.identifiers` | Pages de paramètres (sections `seller`, `vat`, `payment`, `notes`) |

Des actions de grille sont ajoutées à `sylius_admin_channel` (`tmonier_facturx`) et à
`sylius_invoicing_invoice` (`tmonier_facturx_xml`). Les services peuvent être décorés :
`tmonier_facturx.mapper.invoice` (facture → données), `tmonier_facturx.xml.writer` (données → XML),
`tmonier_facturx.pdf.embedder` (PDF + XML → PDF/A-3).

La route de téléchargement du XML (`tmonier_facturx_admin_invoice_download_xml`) vérifie
`InvoiceVoter::ACCESS` (de `sylius/invoicing-plugin`) avant de servir le fichier, à l'image du
contrôle d'accès appliqué au téléchargement du PDF du cœur (`DownloadInvoiceAction`).

## Limitations

- **Pas de transmission à une *plateforme agréée* (PA)** ni d'e-reporting : en France, émettre
  une facture électronique revient à l'envoyer par une PA. Utilisez l'événement ci-dessus pour
  construire un connecteur.
- **Profil EN 16931 uniquement**. Pas d'avoirs (l'Invoicing Plugin n'en a pas), pas de factures
  d'acompte, pas de remises ou de frais au niveau du document (Sylius répartit les promotions
  sur les lignes).
- Les identifiants de l'acheteur sont facultatifs et saisis par un administrateur sur la page
  client ; ils ne sont pas collectés dans le tunnel de commande. Les factures B2C sont des
  documents Factur-X valides, mais en France les ventes B2C font l'objet d'un e-reporting, pas
  d'une facturation électronique.
- Les factures émises avant l'expédition n'ont pas de date de livraison (BT-72) : le groupe de
  livraison du XML est alors vide, ce que les validateurs signalent par l'avertissement
  PEPPOL-EN16931-R008 (et non par une erreur).
- Toute ligne avec un taux de TVA positif relève de la catégorie S ; toutes les lignes sans TVA
  partagent l'unique catégorie configurée sur le canal.
- La conformité PDF/A-3 du résultat dépend du PDF généré par l'Invoicing Plugin : le plugin ajoute
  la pièce jointe, les métadonnées XMP et l'output intent, et corrige les largeurs de glyphes des
  polices TrueType intégrées (wkhtmltopdf les écrit environ 0,7 % trop petites, ce que veraPDF
  rejette), mais il ne réécrit pas les pages. Avec le modèle par défaut et wkhtmltopdf 0.12.6, le
  résultat passe veraPDF (PDF/A-3B) ; vérifiez un échantillon de votre propre modèle avec un
  validateur (voir *Développement*).
- Les PDF déjà stockés ne sont pas modifiés tant que vous n'exécutez pas
  `tmonier:facturx:regenerate-pdfs`.
- Si le document Factur-X ne peut pas être construit (données du vendeur manquantes, PDF
  illisible), le PDF simple est conservé et l'erreur est consignée dans le canal de logs
  `tmonier_facturx`.

**Ce plugin ne constitue pas un conseil juridique ou fiscal.** Il produit un document
techniquement valide à partir des données que vous saisissez et des taxes calculées par Sylius.
Les mentions légales, les taux, catégories et motifs d'exonération de TVA, les données de
l'acheteur et le choix d'une PA restent de la responsabilité du marchand : vérifiez-les avec votre
expert-comptable.

## Développement

```bash
docker compose up -d mysql mailhog   # or your own MySQL
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n

vendor/bin/phpunit --testsuite unit
APP_ENV=test vendor/bin/phpunit --testsuite non-unit   # needs a test database with fixtures
vendor/bin/phpstan analyse
vendor/bin/ecs check
```

**PDF de substitution** : lorsque `WKHTMLTOPDF_PATH` ne pointe pas vers un exécutable wkhtmltopdf,
l'application de test (environnements dev et test) génère chaque facture sous forme d'un PDF FPDF
de substitution d'une page (*Invoice 2026/10/000001*), qui devient tout de même un PDF Factur-X
valide. Pour voir les vraies factures sans installer wkhtmltopdf, utilisez l'enveloppe Docker
(wkhtmltopdf 0.12.6 avec Qt patché), puis videz le cache et générez à nouveau les PDF stockés :

```bash
echo "WKHTMLTOPDF_PATH=$PWD/tests/TestApplication/bin/wkhtmltopdf-docker" >> tests/TestApplication/.env.local
rm -rf var/cache/dev var/cache/test
vendor/bin/console tmonier:facturx:regenerate-pdfs
```

Les tests valident chaque XML généré par rapport au XSD Factur-X EN 16931 et recalculent en PHP les
règles de calcul de l'EN 16931. Les tests fonctionnels écrivent `etc/build/factur-x.pdf` et
`etc/build/factur-x.xml` (et, avec un vrai wkhtmltopdf, `etc/build/factur-x-wkhtmltopdf.pdf`) ;
la CI génère une vraie facture et les valide avec
[Mustang](https://www.mustangproject.org/commandline/) (schematron EN 16931 et veraPDF) :

```bash
java -jar Mustang-CLI-2.26.0.jar --no-notices --action validate --source etc/build/factur-x.pdf
```

<!-- vitrine:demo-b2b:start -->
## Démo B2B

Ce plugin est utilisé dans la [démo B2B Sylius](https://github.com/thibmonier/sylius-b2b-demo) : une boutique Sylius 2.3 avec des données d'exemple fictives, où les plugins B2B fonctionnent ensemble. Le code source, l'installation et les fixtures de la démo sont dans ce dépôt.

La démo est un exemple, pas une configuration de production.
<!-- vitrine:demo-b2b:end -->

## Licence

MIT. Voir [LICENSE](LICENSE).
