<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace T3DOCS\ExceptionCodes;

final class ErrorCodes
{
    private array $codes = [];

    public function __construct(
        array $codes
    ) {
        $this->addCodes($codes);
    }

    public function merge(ErrorCodes $codes): self
    {
        $this->addCodes($codes->codes());

        return $this;
    }

    private function addCodes(array $codes): void
    {
        if ($codes === []) {
            return;
        }
        foreach ($codes AS $code => $value) {
            $code = (int)$code;
            $value = (int)$value;
            if ($code !== $value) {
                continue;
            }

            $this->codes[$code] = $value;
        }

        ksort($this->codes);
    }

    public function codes(): array
    {
       return $this->codes;
    }

    public function total(): int
    {
        return count($this->codes);
    }

    public function export(): array
    {
        return [
            'exceptions' => $this->codes(),
            'total' => $this->total()
        ];
    }
}