<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Model;

/** Invoice note (BG-1): BT-22 text with its BT-21 subject code (UNTDID 4451). */
final class Note
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $subjectCode = null,
    ) {
    }
}
