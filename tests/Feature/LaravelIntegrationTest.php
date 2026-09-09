<?php

namespace YorCreative\DataValidation\Tests\Feature;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YorCreative\DataValidation\DataValidationConfig;
use YorCreative\DataValidation\Laravel\DataValidationServiceProvider;
use YorCreative\DataValidation\Validator;

/**
 * Laravel integration surface: package discovery, config publication and the
 * promise that the core library stays framework-free.
 *
 * NOTE ON SCOPE. The boot() tests below run against a genuine
 * Illuminate\Foundation\Application, which laravel/framework ^12 || ^13
 * provides as a dev dependency. That constraint stops at ^12 on purpose: every
 * laravel/framework release in the ^10 and ^11 ranges is currently withheld by
 * Composer's security-advisory policy, and pulling them in would mean setting
 * `policy.advisories.ignore`. Those two majors are still exercised -- the CI
 * matrix installs the split illuminate/* packages for them, where the advisories
 * do not apply -- they simply cannot contribute a real Application, so the
 * boot() tests do not run there. Everything else in this file is
 * framework-version independent.
 *
 * See LaravelServiceProviderTest for the register()-time bindings.
 */
class LaravelIntegrationTest extends TestCase
{
    private function composerManifest(): array
    {
        $path = dirname(__DIR__, 2) . '/composer.json';
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    // -----------------------------------------------------------------
    // Package discovery
    // -----------------------------------------------------------------

    public function testPackageDiscoveryNamesAProviderThatActuallyExists(): void
    {
        $manifest = $this->composerManifest();

        $providers = $manifest['extra']['laravel']['providers'] ?? null;

        $this->assertIsArray($providers, 'composer.json declares no Laravel providers for auto-discovery.');
        $this->assertNotEmpty($providers);

        foreach ($providers as $provider) {
            $this->assertTrue(
                class_exists($provider),
                "Auto-discovered provider [{$provider}] does not exist; discovery would fatal on boot."
            );
            $this->assertTrue(
                is_subclass_of($provider, ServiceProvider::class),
                "Auto-discovered provider [{$provider}] is not a ServiceProvider."
            );
        }
    }

    public function testTheDiscoveredProviderIsTheOneUnderTest(): void
    {
        $manifest = $this->composerManifest();

        $this->assertContains(
            DataValidationServiceProvider::class,
            $manifest['extra']['laravel']['providers'],
            'The provider covered by these tests is not the one Laravel would discover.'
        );
    }

    // -----------------------------------------------------------------
    // Standalone installation stays free of Laravel
    // -----------------------------------------------------------------

    public function testStandaloneInstallRequiresNoLaravelRuntimePackages(): void
    {
        $manifest = $this->composerManifest();

        $this->assertSame(
            ['php'],
            array_keys($manifest['require']),
            'The runtime requirements must stay PHP-only; a Laravel package here would '
            . 'force the framework on every standalone consumer.'
        );
    }

    public function testTheLaravelIntegrationIsOptionalAndSuggestedOnly(): void
    {
        $manifest = $this->composerManifest();

        foreach (array_keys($manifest['require']) as $package) {
            $this->assertStringStartsNotWith('illuminate/', $package);
            $this->assertStringStartsNotWith('laravel/', $package);
            $this->assertStringStartsNotWith('symfony/', $package);
        }

        $this->assertArrayHasKey(
            'illuminate/support',
            $manifest['suggest'] ?? [],
            'The optional Laravel integration should be advertised via suggest.'
        );
    }

    public function testCoreLibraryCodeDoesNotReferenceLaravel(): void
    {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $laravelDir = $srcDir . '/Laravel';

        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // The Laravel/ subtree is the integration itself and is only ever
            // autoloaded inside a Laravel application.
            if (str_starts_with($file->getPathname(), $laravelDir)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (preg_match('/\b(?:Illuminate|Laravel)\\\\/', $contents)) {
                $offenders[] = str_replace($srcDir . '/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Core library files reference Laravel: ' . implode(', ', $offenders)
        );
    }

    // -----------------------------------------------------------------
    // Config publication
    // -----------------------------------------------------------------

    /**
     * Boot the provider inside a real application and assert what boot() itself
     * registered.
     *
     * An earlier version of this test called ServiceProvider::publishes()
     * directly and then asserted the registration it had just made -- it passed
     * with the provider's boot() body deleted. This drives the real
     * Illuminate\Foundation\Application through register() and boot(), so the
     * publish mapping under assertion is the one boot() actually produced.
     */
    public function testBootRegistersTheConfigPublishMappingInARealApplication(): void
    {
        $this->forgetPublishRegistry();

        $app = $this->bootedApplication();

        $paths = ServiceProvider::pathsToPublish(
            DataValidationServiceProvider::class,
            'data-validation-config'
        );

        $this->assertNotEmpty(
            $paths,
            'boot() registered nothing under the data-validation-config tag.'
        );

        $reflection = new ReflectionClass(DataValidationServiceProvider::class);
        $configPath = $reflection->getMethod('configPath')
            ->invoke(new DataValidationServiceProvider($app));

        $this->assertArrayHasKey(
            $configPath,
            $paths,
            'The published source path is not the packaged config file.'
        );

        $this->assertSame(
            $app->configPath('data-validation.php'),
            $paths[$configPath],
            'The publish target is not the application config path.'
        );
    }

    public function testBootPublishesNothingOutsideTheConsole(): void
    {
        // boot() guards its publishes() call with runningInConsole(). A real
        // application reports false for a non-console run, and nothing should
        // be registered then.
        $this->forgetPublishRegistry();

        $app = $this->bootedApplication(runningInConsole: false);

        $this->assertSame(
            [],
            ServiceProvider::pathsToPublish(
                DataValidationServiceProvider::class,
                'data-validation-config'
            ),
            'boot() registered publishable paths outside of a console run.'
        );

        // register() still ran, so the bindings must be live either way.
        $this->assertInstanceOf(DataValidationConfig::class, $app->make(DataValidationConfig::class));
    }

    public function testRegisterAndBootInARealApplicationProduceAWorkingValidator(): void
    {
        $app = $this->bootedApplication();

        $validator = ($app->make('yorcreative.data-validation'))(
            ['email' => 'nope'],
            ['email' => 'required|email']
        );

        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertFalse($validator->validate());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testTheRealApplicationMergesThePackagedConfigDefaults(): void
    {
        // mergeConfigFrom() only runs inside register(); a real application is
        // what proves the packaged file is actually reachable from it.
        $app = $this->bootedApplication();

        $this->assertSame(
            (new DataValidationConfig())->fieldCacheLimit,
            $app['config']->get('data-validation.field_cache_limit'),
            'The packaged config was not merged into the application config.'
        );
    }

    /**
     * A genuine Illuminate\Foundation\Application with the provider registered
     * and booted. Not a double: this is the class Laravel itself runs.
     */
    private function bootedApplication(?bool $runningInConsole = null): Application
    {
        // The Laravel 10 and 11 matrix jobs install the split illuminate/*
        // packages, which ship the Application contract but no concrete
        // application. Skip rather than fail there: the provider's behaviour on
        // those majors is covered by the container-level tests.
        if (! class_exists(Application::class)) {
            $this->markTestSkipped(
                'laravel/framework is not installed; a real Application is unavailable on this dependency set.'
            );
        }

        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new ConfigRepository([]));

        // Left at null, the real Application decides for itself from PHP_SAPI,
        // which under PHPUnit is 'cli' -- so the console branch of boot() runs
        // without being told to. Only the non-console case needs to be set, and
        // it is set on the real class's own property, not on a substitute.
        if ($runningInConsole !== null) {
            (new ReflectionClass(Application::class))
                ->getProperty('isRunningInConsole')
                ->setValue($app, $runningInConsole);
        }

        $provider = new DataValidationServiceProvider($app);
        $provider->register();
        $provider->boot();

        return $app;
    }

    private function forgetPublishRegistry(): void
    {
        $reflection = new ReflectionClass(ServiceProvider::class);

        foreach (['publishes', 'publishGroups'] as $property) {
            if ($reflection->hasProperty($property)) {
                $reflection->getProperty($property)->setValue(null, []);
            }
        }
    }

    public function testThePublishedConfigMatchesTheCompiledDefaults(): void
    {
        // If the shipped config file drifts from DataValidationConfig, publishing
        // it silently changes behaviour for anyone who runs vendor:publish.
        $reflection = new ReflectionClass(DataValidationServiceProvider::class);
        $configPath = $reflection->getMethod('configPath')
            ->invoke(new DataValidationServiceProvider(new Container()));

        $published = require $configPath;
        $defaults = new DataValidationConfig();

        $this->assertSame(
            $defaults->fieldCacheLimit,
            $published['field_cache_limit'],
            'field_cache_limit in the published config drifted from the compiled default.'
        );
        $this->assertSame(
            $defaults->parsedRulesCache,
            $published['parsed_rules_cache'],
            'parsed_rules_cache in the published config drifted from the compiled default.'
        );
        $this->assertSame(
            $defaults->chunkSize,
            $published['chunk_size'],
            'chunk_size in the published config drifted from the compiled default.'
        );
    }

    public function testPublishingThenRegisteringYieldsTheCompiledDefaults(): void
    {
        // The end-to-end shape of `vendor:publish` followed by a boot: the file
        // that would land in the application's config directory, fed back through
        // the provider, must reproduce the library's own defaults.
        $reflection = new ReflectionClass(DataValidationServiceProvider::class);
        $configPath = $reflection->getMethod('configPath')
            ->invoke(new DataValidationServiceProvider(new Container()));

        $app = new Container();
        $app->instance('config', new ConfigRepository(['data-validation' => require $configPath]));
        (new DataValidationServiceProvider($app))->register();

        $cfg = $app->make(DataValidationConfig::class);
        $defaults = new DataValidationConfig();

        $this->assertSame($defaults->fieldCacheLimit, $cfg->fieldCacheLimit);
        $this->assertSame($defaults->parsedRulesCache, $cfg->parsedRulesCache);
        $this->assertSame($defaults->chunkSize, $cfg->chunkSize);
    }

    // -----------------------------------------------------------------
    // Overrides and the factory binding, through the container
    // -----------------------------------------------------------------

    public function testOverriddenConfigReachesTheValidatorProducedByTheFactory(): void
    {
        $app = new Container();
        $app->instance('config', new ConfigRepository([
            'data-validation' => [
                'field_cache_limit' => 111,
                'parsed_rules_cache' => 222,
                'chunk_size' => 333,
            ],
        ]));
        (new DataValidationServiceProvider($app))->register();

        $validator = ($app->make('yorcreative.data-validation'))(
            ['a' => 'x'],
            ['a' => 'required']
        );

        $this->assertInstanceOf(Validator::class, $validator);

        $config = (new ReflectionClass($validator))->getProperty('config')->getValue($validator);

        $this->assertInstanceOf(DataValidationConfig::class, $config);
        $this->assertSame(111, $config->fieldCacheLimit);
        $this->assertSame(222, $config->parsedRulesCache);
        $this->assertSame(333, $config->chunkSize);
    }

    public function testNullChunkSizeReachesTheValidatorAndSelectsTheHeuristic(): void
    {
        $app = new Container();
        $app->instance('config', new ConfigRepository([
            'data-validation' => ['chunk_size' => null],
        ]));
        (new DataValidationServiceProvider($app))->register();

        $validator = ($app->make('yorcreative.data-validation'))(
            ['rows' => [['v' => 1], ['v' => 2]]],
            ['rows.*.v' => 'required']
        );

        $config = (new ReflectionClass($validator))->getProperty('config')->getValue($validator);
        $this->assertNull($config->chunkSize, 'A null chunk_size must survive to the Validator.');

        // And the heuristic still produces a working validation run.
        $this->assertTrue($validator->validate());
    }

    public function testFactoryHonoursStopOnFirstError(): void
    {
        $app = new Container();
        $app->instance('config', new ConfigRepository(['data-validation' => []]));
        (new DataValidationServiceProvider($app))->register();

        $factory = $app->make('yorcreative.data-validation');

        $validator = $factory(
            ['a' => 'bad', 'b' => 'bad'],
            ['a' => 'email', 'b' => 'email'],
            [],
            [],
            true
        );

        $this->assertFalse($validator->validate());
        $this->assertCount(1, $validator->errors(), 'stopOnFirstError did not reach the Validator.');
    }
}
