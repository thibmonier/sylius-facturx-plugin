<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Validator\Constraints\CompanyIdentifier;

final class TranslationsTest extends TestCase
{
    private const LOCALES = ['en', 'fr', 'de', 'es', 'it', 'nl', 'pl'];

    private const DOMAINS = ['messages', 'flashes', 'validators'];

    /** Keys built at runtime, not visible as literals in the code. */
    private const DYNAMIC_KEY_PREFIXES = ['tmonier_facturx.operation_category.', 'tmonier_facturx.vat_category.'];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        foreach (self::DOMAINS as $domain) {
            foreach (self::LOCALES as $locale) {
                yield sprintf('%s.%s', $domain, $locale) => [$domain, $locale];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testEveryLocaleDefinesTheSameKeys(string $domain, string $locale): void
    {
        $reference = $this->flatten($this->parse($domain, 'en'));
        $catalogue = $this->flatten($this->parse($domain, $locale));

        self::assertSame([], array_values(array_diff(array_keys($reference), array_keys($catalogue))), 'Missing keys');
        self::assertSame([], array_values(array_diff(array_keys($catalogue), array_keys($reference))), 'Unknown keys');
        foreach ($catalogue as $key => $value) {
            self::assertNotSame('', trim($value), sprintf('Empty translation "%s"', $key));
            preg_match_all('/%\w+%/', $reference[$key], $expected);
            preg_match_all('/%\w+%/', $value, $actual);
            self::assertEqualsCanonicalizing($expected[0], $actual[0], sprintf('Placeholders of "%s"', $key));
        }
    }

    public function testEveryKeyUsedInTheCodeIsTranslated(): void
    {
        $keys = [];
        foreach (self::DOMAINS as $domain) {
            $keys += $this->flatten($this->parse($domain, 'en'));
        }

        $finder = (new Finder())->files()->in([__DIR__ . '/../../src', __DIR__ . '/../../templates', __DIR__ . '/../../config'])->name(['*.php', '*.twig', '*.yaml']);
        $used = [];
        foreach ($finder as $file) {
            preg_match_all("/['\"](tmonier_facturx\\.(?:ui|form(?!\\.type\\.)|operation_category|vat_category)\\.[a-z0-9_.]+|tmonier_facturx\\.[a-z_]+_saved|tmonier_facturx\\.cannot_[a-z_]+)['\"]/", $file->getContents(), $matches);
            array_push($used, ...$matches[1]);
        }

        foreach (ChannelSettingsInterface::OPERATION_CATEGORIES as $category) {
            $used[] = self::DYNAMIC_KEY_PREFIXES[0] . $category;
        }
        foreach (ChannelSettingsInterface::ZERO_VAT_CATEGORIES as $category) {
            $used[] = self::DYNAMIC_KEY_PREFIXES[1] . $category;
        }
        foreach ([CompanyIdentifier::TYPE_SIREN, CompanyIdentifier::TYPE_SIRET, CompanyIdentifier::TYPE_VAT_NUMBER] as $type) {
            $used[] = (new CompanyIdentifier($type))->getMessage();
        }

        self::assertGreaterThan(40, count(array_unique($used)));
        self::assertSame([], array_values(array_diff(array_unique($used), array_keys($keys))), 'Keys used in the code but not translated');
    }

    /** @return array<mixed> */
    private function parse(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(sprintf('%s/../../translations/%s.%s.yaml', __DIR__, $domain, $locale));
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix . $key . '.');

                continue;
            }

            $flat[$prefix . $key] = is_scalar($value) ? (string) $value : '';
        }

        return $flat;
    }
}
