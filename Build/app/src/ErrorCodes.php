<?php

declare(strict_types=1);

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