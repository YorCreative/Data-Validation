<?php

namespace YorCreative\DataValidation\Tests\Feature;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YorCreative\DataValidation\DataValidationConfig;
use YorCreative\DataValidation\Laravel\DataValidationServiceProvider;
use YorCreative\DataValidation\Validator;

/**
 * Verifies the optional Laravel integration without booting a full app.
 *
 * The test wires up a minimal Container + Config repository, runs the
 * service provider's register() step, and asserts that the bindings work
 * end-to-end. The core library remains framework-agnostic; this test only
 * runs because illuminate/support and illuminate/config are dev dependencies.
 */
class LaravelServiceProviderTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        $this->app->instance('config', new ConfigRepository([
            'data-validation' => [
                'field_cache_limit' => 2500,
                'parsed_rules_cache' => 750,
                'chunk_size' => 800,
            ],
        ]));

        $provider = new DataValidationServiceProvider($this->app);
        $provider->register();
    }

    public function testConfigSingletonReflectsPublishedValues(): void
    {
        /** @var DataValidationConfig $cfg */
        $cfg = $this->app->make(DataValidationConfig::class);

        $this->assertSame(2500, $cfg->fieldCacheLimit);
        $this->assertSame(750, $cfg->parsedRulesCache);
        $this->assertSame(800, $cfg->chunkSize);
    }

    public function testConfigSingletonIsShared(): void
    {
        $first = $this->app->make(DataValidationConfig::class);
        $second = $this->app->make(DataValidationConfig::class);

        $this->assertSame($first, $second);
    }

    public function testValidatorFactoryProducesValidatorUsingContainerConfig(): void
    {
        /** @var callable $factory */
        $factory = $this->app->make('yorcreative.data-validation');

        $validator = $factory(
            ['email' => 'nope'],
            ['email' => 'required|email']
        );

        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertFalse($validator->validate());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testRegisterUsesDefaultsWhenConfigKeysMissing(): void
    {
        $bareApp = new Container();
        $bareApp->instance('config', new ConfigRepository(['data-validation' => []]));
        (new DataValidationServiceProvider($bareApp))->register();

        /** @var DataValidationConfig $cfg */
        $cfg = $bareApp->make(DataValidationConfig::class);

        // These match the defaults declared in DataValidationConfig.
        $this->assertSame(1000, $cfg->fieldCacheLimit);
        $this->assertSame(500, $cfg->parsedRulesCache);
        $this->assertSame(500, $cfg->chunkSize);
    }

    public function testRegisterAllowsNullChunkSizeForDynamicHeuristic(): void
    {
        $app = new Container();
        $app->instance('config', new ConfigRepository([
            'data-validation' => [
                'chunk_size' => null,
            ],
        ]));
        (new DataValidationServiceProvider($app))->register();

        /** @var DataValidationConfig $cfg */
        $cfg = $app->make(DataValidationConfig::class);

        $this->assertNull($cfg->chunkSize);
    }

    /**
     * boot()'s runningInConsole()/publishes() wiring needs an application
     * exposing runningInConsole() and configPath(), which a bare
     * Illuminate\Container\Container does not provide and which we will not
     * fake with a test double (no mocks). What CAN break silently without an
     * app is configPath() itself: if it resolved to a nonexistent file,
     * `vendor:publish --tag=data-validation-config` would publish nothing
     * while still appearing to succeed. This test guards that.
     */
    public function testConfigPathResolvesToAReadablePublishableConfigFile(): void
    {
        $reflection = new ReflectionClass(DataValidationServiceProvider::class);
        $method = $reflection->getMethod('configPath');

        $provider = new DataValidationServiceProvider($this->app);
        $path = $method->invoke($provider);

        $this->assertFileExists($path);
        $this->assertIsReadable($path);

        $config = require $path;

        $this->assertIsArray($config);
        $this->assertArrayHasKey('field_cache_limit', $config);
        $this->assertArrayHasKey('parsed_rules_cache', $config);
        $this->assertArrayHasKey('chunk_size', $config);
    }
}
