<?php

namespace YorCreative\DataValidation\Laravel;

use Illuminate\Support\ServiceProvider;
use YorCreative\DataValidation\DataValidationConfig;
use YorCreative\DataValidation\Validator;

/**
 * Optional Laravel integration for YorCreative\DataValidation.
 *
 * The core library has zero runtime dependencies on Laravel. This provider
 * is only loaded when the package is installed inside a Laravel application
 * (via package auto-discovery). Outside of Laravel this file is never
 * autoloaded, so the Illuminate type references below have no effect.
 */
class DataValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->configPath(),
            'data-validation'
        );

        $this->app->singleton(DataValidationConfig::class, function ($app): DataValidationConfig {
            $cfg = new DataValidationConfig();
            $values = (array) $app['config']->get('data-validation', []);

            if (array_key_exists('field_cache_limit', $values)) {
                $cfg->fieldCacheLimit = (int) $values['field_cache_limit'];
            }
            if (array_key_exists('parsed_rules_cache', $values)) {
                $cfg->parsedRulesCache = (int) $values['parsed_rules_cache'];
            }
            if (array_key_exists('chunk_size', $values)) {
                $cfg->chunkSize = $values['chunk_size'] === null ? null : (int) $values['chunk_size'];
            }

            return $cfg;
        });

        $this->app->bind('yorcreative.data-validation', function ($app): callable {
            return static function (
                array $data,
                array $rules,
                array $messages = [],
                array $attributes = [],
                bool $stopOnFirstError = false
            ) use ($app): Validator {
                return Validator::make(
                    $data,
                    $rules,
                    $messages,
                    $attributes,
                    $stopOnFirstError,
                    $app->make(DataValidationConfig::class)
                );
            };
        });
    }

    // NOTE: the runningInConsole()/publishes() wiring below is not covered by
    // the test suite. Exercising it needs an application object exposing
    // runningInConsole() and configPath(), which a bare
    // Illuminate\Container\Container does not provide; building one for the
    // test would be a test double, which this project's no-mocks constraint
    // forbids. LaravelServiceProviderTest::
    // testConfigPathResolvesToAReadablePublishableConfigFile instead covers
    // the one part of this that can break silently: configPath() resolving
    // to a file that does not exist.
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('data-validation.php'),
            ], 'data-validation-config');
        }
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/data-validation.php';
    }
}
