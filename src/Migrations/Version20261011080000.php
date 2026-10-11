<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261011080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Factur-X channel settings and customer identifiers tables (tmonier/sylius-facturx-plugin).';
    }

    public function up(Schema $schema): void
    {
        $settings = $schema->createTable('tmonier_facturx_channel_settings');
        $settings->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $settings->addColumn('channel_id', Types::INTEGER);
        $settings->addColumn('enabled', Types::BOOLEAN);
        $settings->addColumn('legal_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('siren', Types::STRING, ['length' => 9, 'notnull' => false]);
        $settings->addColumn('siret', Types::STRING, ['length' => 14, 'notnull' => false]);
        $settings->addColumn('vat_number', Types::STRING, ['length' => 32, 'notnull' => false]);
        $settings->addColumn('legal_form', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('trade_register', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('electronic_address', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('electronic_address_scheme', Types::STRING, ['length' => 8, 'notnull' => false]);
        $settings->addColumn('payment_terms', Types::TEXT, ['notnull' => false]);
        $settings->addColumn('payment_due_days', Types::INTEGER);
        $settings->addColumn('iban', Types::STRING, ['length' => 34, 'notnull' => false]);
        $settings->addColumn('bic', Types::STRING, ['length' => 11, 'notnull' => false]);
        $settings->addColumn('operation_category', Types::STRING, ['length' => 16]);
        $settings->addColumn('vat_on_debits', Types::BOOLEAN);
        $settings->addColumn('zero_vat_category', Types::STRING, ['length' => 2]);
        $settings->addColumn('vat_exemption_reason', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('vat_exemption_reason_code', Types::STRING, ['length' => 32, 'notnull' => false]);
        $settings->addColumn('late_payment_penalties_note', Types::TEXT, ['notnull' => false]);
        $settings->addColumn('recovery_indemnity_note', Types::TEXT, ['notnull' => false]);
        $settings->addColumn('early_payment_discount_note', Types::TEXT, ['notnull' => false]);
        $settings->setPrimaryKey(['id']);
        $settings->addUniqueIndex(['channel_id'], 'UNIQ_597182CE72F5A1AA');
        $settings->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_597182CE72F5A1AA');

        $identifiers = $schema->createTable('tmonier_facturx_customer_identifiers');
        $identifiers->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $identifiers->addColumn('customer_id', Types::INTEGER);
        $identifiers->addColumn('company_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $identifiers->addColumn('siren', Types::STRING, ['length' => 9, 'notnull' => false]);
        $identifiers->addColumn('vat_number', Types::STRING, ['length' => 32, 'notnull' => false]);
        $identifiers->addColumn('electronic_address', Types::STRING, ['length' => 255, 'notnull' => false]);
        $identifiers->addColumn('electronic_address_scheme', Types::STRING, ['length' => 8, 'notnull' => false]);
        $identifiers->setPrimaryKey(['id']);
        $identifiers->addUniqueIndex(['customer_id'], 'UNIQ_6C1091B89395C3F3');
        $identifiers->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_6C1091B89395C3F3');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_facturx_customer_identifiers');
        $schema->dropTable('tmonier_facturx_channel_settings');
    }
}
