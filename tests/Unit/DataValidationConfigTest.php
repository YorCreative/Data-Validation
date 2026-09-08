<?php

namespace YorCreative\DataValidation\Tests;

use PHPUnit\Framework\TestCase;
use YorCreative\DataValidation\DataValidationConfig;

class DataValidationConfigTest extends TestCase
{
    public function testDefaultValues()
    {
        $config = new DataValidationConfig();
        $this->assertEquals(1000, $config->fieldCacheLimit);
        $this->assertEquals(500, $config->parsedRulesCache);
        $this->assertEquals(500, $config->chunkSize);
    }

    public function testOptimizeForLargeDatasetWithZeroItems()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(0);
        $this->assertEquals(1000, $config->fieldCacheLimit);
        $this->assertEquals(500, $config->parsedRulesCache);
        $this->assertEquals(500, $config->chunkSize);
    }

    public function testOptimizeForLargeDatasetWithSmallItems()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(1000);
        $this->assertEquals(1000, $config->fieldCacheLimit);
        $this->assertEquals(500, $config->parsedRulesCache);
        $this->assertEquals(500, $config->chunkSize);
    }

    public function testOptimizeForLargeDatasetWithMediumItems()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(2002);
        $this->assertEquals(1001, $config->fieldCacheLimit); // 2002 / 2 = 1001
        $this->assertEquals(500, $config->parsedRulesCache); // max(500, 2002 / 20) = max(500, 100.1) = 500
        $this->assertEquals(500, $config->chunkSize); // max(500, min(10000, 2002 / 100)) = max(500, 20.02) = 500
    }

    public function testOptimizeForLargeDatasetWithLargeItems()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(100000);
        $this->assertEquals(50000, $config->fieldCacheLimit); // max(1000, 100000 / 2) = 50000
        $this->assertEquals(5000, $config->parsedRulesCache); // max(500, 100000 / 20) = 5000
        $this->assertEquals(1000, $config->chunkSize); // max(500, min(10000, 100000 / 100)) = max(500, 1000) = 1000
    }

    public function testOptimizeForLargeDatasetWithVeryLargeItems()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(1000000);
        $this->assertEquals(500000, $config->fieldCacheLimit); // max(1000, 1000000 / 2) = 500000
        $this->assertEquals(50000, $config->parsedRulesCache); // max(500, 1000000 / 20) = 50000
        $this->assertEquals(10000, $config->chunkSize); // max(500, min(10000, 1000000 / 100)) = max(500, 10000) = 10000
    }

    public function testOptimizeForLargeDatasetWithChunkSizeCap()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(2000000);
        $this->assertEquals(1000000, $config->fieldCacheLimit); // max(1000, 2000000 / 2) = 1000000
        $this->assertEquals(100000, $config->parsedRulesCache); // max(500, 2000000 / 20) = 100000
        $this->assertEquals(10000, $config->chunkSize); // max(500, min(10000, 2000000 / 100)) = max(500, 10000) = 10000
    }

    public function testOptimizeForLargeDatasetWithNonIntegerFieldCacheLimit()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(2001);
        $this->assertEquals(1000, $config->fieldCacheLimit); // max(1000, 2001 / 2) = max(1000, 1000.5) = 1000.5, truncated to 1000
        $this->assertEquals(500, $config->parsedRulesCache);
        $this->assertEquals(500, $config->chunkSize);
    }

    public function testOptimizeForLargeDatasetWithNonIntegerParsedRulesCache()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(10001);
        $this->assertEquals(5000, $config->fieldCacheLimit); // max(1000, 10001 / 2) = 5000.5, truncated to 5000
        $this->assertEquals(500, $config->parsedRulesCache); // max(500, 10001 / 20) = max(500, 500.05) = 500.05, truncated to 500
        $this->assertEquals(500, $config->chunkSize);
    }

    public function testOptimizeForLargeDatasetWithNonIntegerChunkSize()
    {
        $config = new DataValidationConfig();
        $config->optimizeForLargeDataset(50100);
        $this->assertEquals(25050, $config->fieldCacheLimit); // max(1000, 50100 / 2) = 25050
        $this->assertEquals(2505, $config->parsedRulesCache); // max(500, 50100 / 20) = 2505
        $this->assertEquals(501, $config->chunkSize); // max(500, min(10000, 50100 / 100)) = max(500, 501) = 501
    }

    public function testChunkSizeCanBeNullToEnableDynamicHeuristic()
    {
        $config = new DataValidationConfig();
        $config->chunkSize = null;

        $this->assertNull($config->chunkSize);
    }
}
