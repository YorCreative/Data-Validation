<?php

namespace YorCreative\DataValidation;

class DataValidationConfig
{
    public int $fieldCacheLimit = 1000;
    public int $parsedRulesCache = 500;
    public ?int $chunkSize = 500;

    public function optimizeForLargeDataset(int $totalItems): void
    {
        $this->fieldCacheLimit = max(1000, $totalItems / 2); // e.g., 50,000 for 100,000 items
        $this->parsedRulesCache = max(500, $totalItems / 20); // e.g., 5,000 for 100,000 items

        // Dynamic chunk size: between 500 and 10,000
        $this->chunkSize = max(500, min(10000, $totalItems / 100));
    }
}
