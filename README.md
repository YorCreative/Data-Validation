<div align="center">
  <a href="https://github.com/YorCreative">
    <img src="content/data-validation-logo.png" alt="Logo" width="257" height="256">
  </a>
</div>

<div align="center">
<a href="https://github.com/YorCreative/Data-Validation/blob/main/LICENSE.md"><img alt="GitHub license" src="https://img.shields.io/github/license/YorCreative/Data-Validation"></a>
<a href="https://github.com/YorCreative/Data-Validation/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/YorCreative/Data-Validation?label=Repo%20Stars"></a>
<img alt="GitHub Org's stars" src="https://img.shields.io/github/stars/YorCreative?style=social&label=YorCreative%20Stars&link=https%3A%2F%2Fgithub.com%2FYorCreative">
<a href="https://github.com/YorCreative/Data-Validation/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/YorCreative/Data-Validation"></a>
<a href="https://github.com/YorCreative/Data-Validation/network"><img alt="GitHub forks" src="https://img.shields.io/github/forks/YorCreative/Data-Validation"></a>
<a href="https://github.com/YorCreative/Data-Validation/actions/workflows/phpunit.yml"><img alt="PHPUnit" src="https://github.com/YorCreative/Data-Validation/actions/workflows/phpunit.yml/badge.svg"></a>
</div>

DataValidation is a lightweight, high-performance PHP validation library engineered for enterprise-grade applications. Outperforming alternatives with up to 61.8x faster validation for invalid data, it excels in speed and memory efficiency while seamlessly handling complex nested data with wildcards. With no dependencies, comprehensive test coverage, and a developer-friendly API, DataValidation empowers teams to build scalable, secure systems with confidence.

## Why DataValidation?

DataValidation sets a new standard for PHP validation with its performance and flexibility:

- **Unmatched Speed**: Validates 5000 items in 1.499 seconds for valid data, up to 5.2x faster than Laravel’s Illuminate\Validation (7.724 seconds). For invalid data, it’s up to 61.8x faster (1.502 seconds vs. 92.814 seconds).
- **Memory Efficiency**: Maintains a low memory footprint, with peak usage of 10.000 MB for 5000 items, compared to 25.500 MB for alternatives.
- **Wildcard Support**: Effortlessly handles nested arrays with wildcards (e.g., `users.*.email`), ideal for complex API payloads.
- **Extensibility**: Supports custom rules via closures or classes, enabling tailored validation logic.

## Installation

Install via Composer:

```bash
composer require yorcreative/data-validation
```

**Requirements**:
- PHP 8.3, 8.4, or 8.5

### Optional Laravel Integration

The core library has **zero runtime framework dependencies**. When the package is installed inside a Laravel 10, 11, 12, or 13 application, an optional service provider (`YorCreative\DataValidation\Laravel\DataValidationServiceProvider`) is auto-discovered and binds:

- A shared `DataValidationConfig` singleton, hydrated from `config/data-validation.php`
- A `yorcreative.data-validation` factory closure that returns a `Validator` pre-configured from the container

Publish the config file to customize cache limits and chunk size:

```bash
php artisan vendor:publish --tag=data-validation-config
```

Set `chunk_size` to `null` if you want the validator to fall back to its dynamic wildcard chunking heuristic instead of a fixed override.

Outside of Laravel the provider class is never loaded — the library remains fully framework-agnostic.

## Basic Usage

Validate data with a clean, intuitive API adhering to PSR-12 standards:

```php
use YorCreative\DataValidation\Validator;

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com'
];
$rules = [
    'name' => 'required|string',
    'email' => 'required|email'
];

$validator = Validator::make($data, $rules);
if ($validator->validate()) {
    echo "Validation passed!";
} else {
    print_r($validator->errors());
}
```

## Supported Rules

DataValidation provides a comprehensive set of validation rules to meet diverse business needs:

| Rule                         | Description                                              | Example                                       |
|------------------------------|----------------------------------------------------------|-----------------------------------------------|
| `string`                     | Must be a string.                                        | `'name' => 'string'`                          |
| `numeric`                    | Must be numeric.                                         | `'price' => 'numeric'`                        |
| `integer`                    | Must be an integer.                                      | `'count' => 'integer'`                        |
| `email`                      | Must be a valid email.                                   | `'email' => 'email'`                          |
| `array`                      | Must be an array.                                        | `'items' => 'array'`                          |
| `min:value`                  | Minimum value/length.                                    | `'age' => 'min:18'`                           |
| `max:value`                  | Maximum value/length.                                    | `'age' => 'max:65'`                           |
| `in:a,b,c`                   | Must be one of the listed values.                        | `'role' => 'in:admin,editor'`                 |
| `not_in:a,b,c`               | Must not be one of the listed values.                    | `'role' => 'not_in:banned'`                   |
| `same:field`                 | Must match another field.                                | `'password_confirmation' => 'same:password'`  |
| `different:field`            | Must differ from another field.                          | `'old_password' => 'different:new_password'`  |
| `date`                       | Must be a valid date.                                    | `'dob' => 'date'`                             |
| `regex:pattern`              | Must match the regex pattern.                            | `'slug' => 'regex:/^[a-z0-9-]+$/'`            |
| `boolean`                    | Must be a boolean.                                       | `'active' => 'boolean'`                       |
| `between:min,max`            | Must be between two values.                              | `'score' => 'between:1,100'`                  |
| `digits:length`              | Must be a number with exact digit length.                | `'phone' => 'digits:10'`                      |
| `starts_with:a,b`            | Must start with one of the values.                       | `'prefix' => 'starts_with:AB,CD'`             |
| `ends_with:a,b`              | Must end with one of the values.                         | `'filename' => 'ends_with:.jpg,.png'`         |
| `date_format:format`         | Must match the date format.                              | `'published_at' => 'date_format:Y-m-d'`       |
| `ip`                         | Must be a valid IP address.                              | `'ip_address' => 'ip'`                        |
| `ipv4`                       | Must be a valid IPv4 address.                            | `'ip' => 'ipv4'`                              |
| `ipv6`                       | Must be a valid IPv6 address.                            | `'ip' => 'ipv6'`                              |
| `url`                        | Must be a valid URL.                                     | `'website' => 'url'`                          |
| `json`                       | Must be valid JSON.                                      | `'config' => 'json'`                          |
| `uuid`                       | Must be a valid UUID.                                    | `'id' => 'uuid'`                              |
| `required`                   | Field must be present and not empty.                     | `'name' => 'required'`                        |
| `nullable`                   | Allows null values, skips other rules if null.           | `'middle_name' => 'nullable\|string'`         |
| `required_if:field,value`    | Required if another field equals a value.                | `'state' => 'required_if:country,US'`         |
| `required_unless:field,value`| Required unless another field equals a value.            | `'passport' => 'required_unless:citizen,yes'` |

**Note**: Unknown rules or invalid parameters throw an `InvalidArgumentException` during initialization, ensuring early detection of configuration issues.

## Advanced Usage

### Validating Nested Data with Wildcards

Efficiently validate complex nested data structures using wildcards, ideal for API-driven applications:

```php
use YorCreative\DataValidation\Validator;

$data = [
    'users' => [
        ['profile' => ['email' => 'user1@example.com', 'confirm_email' => 'user1@example.com']],
        ['profile' => ['email' => 'user2@example.com', 'confirm_email' => 'user2@example.com']]
    ]
];
$rules = [
    'users.*.profile.email' => 'required|email|same:users.*.profile.confirm_email'
];

$validator = Validator::make($data, $rules);
if ($validator->validate()) {
    echo "All user emails are valid and match their confirmations!";
} else {
    print_r($validator->errors());
}
```

### Custom Validation Rules with Closures

Enable rapid prototyping of business-specific rules using closures:

```php
use YorCreative\DataValidation\Validator;

$validator = Validator::make(['score' => 85], [
    'score' => [
        function ($field, $value, $fail) {
            if ($value < 90) {
                $fail("The {$field} must be at least 90 to pass the elite threshold.");
            }
        }
    ]
]);
```

### Custom Error Messages

Tailor error messages to enhance user experience and align with business requirements:

```php
use YorCreative\DataValidation\Validator;

$messages = [
    'name.required' => 'Please provide a name.',
    'email.email' => 'Enter a valid email address.'
];
$validator = Validator::make(
    ['name' => '', 'email' => 'invalid'],
    ['name' => 'required', 'email' => 'email'],
    $messages
);
print_r($validator->errors());
```

### Custom Attribute Names

Improve error message clarity with user-friendly attribute names:

```php
use YorCreative\DataValidation\Validator;

$attributes = ['user.email' => 'Email Address', 'user.name' => 'Full Name'];
$validator = Validator::make(
    ['user' => ['email' => 'invalid', 'name' => '']],
    ['user.email' => 'email', 'user.name' => 'required'],
    [],
    $attributes
);
print_r($validator->errors());
```

### Stopping on First Error

Optimize performance in latency-sensitive applications by halting validation after the first error:

```php
use YorCreative\DataValidation\Validator;

$validator = Validator::make($data, $rules, [], [], true);
if ($validator->fails()) {
    echo "Validation stopped at first error: " . implode(', ', $validator->errors()['user.email']);
}
```

`fails()` and `passes()` trigger validation on first use, so they can be used directly when you do not want to call `validate()` yourself.

## Extending with Custom Rules

For applications requiring bespoke validation logic, implement custom rules via `ValidationRuleInterface`:

```php
namespace App\Rules;

use YorCreative\DataValidation\Rules\ValidationRuleInterface;

class PhoneNumberRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        return preg_match('/^\+?1?\d{10}$/', $value) === 1;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? sprintf('The %s must be a valid US phone number.', $field);
    }

    public function validateParameters(string $field, array $parameters): void
    {
    }
}
```

Register the custom rule directory for seamless integration:

```php
use YorCreative\DataValidation\Rules\RuleRegistry;

RuleRegistry::registerCustomRuleDirectory(__DIR__ . '/Rules');
```

Use it in validation workflows:

```php
use YorCreative\DataValidation\Validator;

$validator = Validator::make(
    ['phone' => '1234567890'],
    ['phone' => 'required|phone_number']
);
```

Alternatively, register a closure-based rule:

```php
use YorCreative\DataValidation\Rules\RuleRegistry;

RuleRegistry::registerClosureRule('phone', function ($value) {
    return preg_match('/^\+?1?\d{10}$/', $value) === 1;
}, 'The :attribute must be a valid US phone number.');
```

## Performance Benchmarks

DataValidation is optimized for speed and efficiency, particularly for large datasets and wildcard validations. Benchmarks were conducted on an Apple M1 chip with 10 cores, running PHP 8.4, ensuring optimal conditions for performance evaluation. The following results compare DataValidation (DV) with Laravel's Illuminate\Validation (IL) across different dataset sizes.

### Benchmark Results for Valid Data

| Items | DV Time (s) | DV Mem (MB) | IL Time (s) | IL Mem (MB) |
|-------|-------------|-------------|-------------|-------------|
| 1000  | 0.069       | 4.000       | 0.884       | 8.000       |
| 2000  | 0.249       | 6.000       | 2.112       | 4.000       |
| 3000  | 0.538       | 6.000       | 3.570       | 8.000       |
| 5000  | 1.499       | 10.000      | 7.724       | 25.500      |

### Benchmark Results for Invalid Data

| Items | DV Time (s) | DV Mem (MB) | IL Time (s) | IL Mem (MB) |
|-------|-------------|-------------|-------------|-------------|
| 1000  | 0.065       | 6.000       | 5.984       | 26.000      |
| 2000  | 0.255       | 4.000       | 16.964      | 36.000      |
| 3000  | 0.559       | 4.000       | 34.397      | 56.000      |
| 5000  | 1.502       | 12.000      | 92.814      | 110.000     |

### Scalability for Large Datasets

For extremely large datasets, such as 100,000 items, YorCreative\DataValidation completes validation in approximately 9.2 minutes (553.462 seconds for valid data) with a peak memory usage of 262.012 MB, demonstrating its ability to handle massive amounts of data efficiently. This scalability is crucial for enterprise-grade batch processing, ensuring reliability and performance in production environments.

### Key Definitions

- **DV (DataValidation)**: The YorCreative\DataValidation library, optimized for lightweight, high-performance validation tailored to enterprise needs.
- **IL (Illuminate\Validation)**: Laravel’s validation component, a robust but resource-intensive alternative commonly used in PHP ecosystems.
- **Time (s)**: Time in seconds to validate the dataset.
- **Mem (MB)**: Peak memory usage in megabytes during validation.
- **Items**: Number of records in the dataset (e.g., 1000 users, each with profile and settings data).

### Context and Insights

- **Performance Advantage**: DataValidation delivers up to 61.8x faster processing for invalid data (1.502 s vs. 92.814 s for 5000 items) and 5.2x for valid data (1.499 s vs. 7.724 s), reducing latency in critical API endpoints.
- **Memory Efficiency**: Maintains a low memory footprint (10.000 MB peak for 5000 items), compared to Illuminate\Validation’s 25.500 MB for valid data and 110.000 MB for invalid data, enabling cost-effective scaling.
- **Wildcard Optimization**: Leverages stack-based traversal and caching to handle wildcard rules (e.g., `users.*.email`), addressing performance bottlenecks in Laravel.

### Running Benchmarks

To validate performance in your environment:

```bash
composer benchmark-all
```

This command executes benchmarks for both DataValidation and Illuminate\Validation, providing detailed metrics. Ensure sufficient memory (4GB recommended) for accurate results.

## Contributing

Contributions are welcome! Fork the repository, create a feature branch, and submit a pull request ensuring tests pass.

DataValidation includes extensive unit and feature tests for reliability.

```bash
composer test
```

Report issues or suggest features on the [GitHub repository](https://github.com/yorcreative/data-validation).

## License

DataValidation is licensed under the MIT License. See the [LICENSE](https://github.com/yorcreative/data-validation/blob/main/LICENSE) file for details.
