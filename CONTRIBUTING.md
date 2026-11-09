# Contributing

Thanks for helping. Bug reports and pull requests are welcome at
https://github.com/Checkslip/sdk-php.

## Setup

You need PHP 8.2 or later and Composer.

```sh
composer install
composer test      # phpunit
composer stan      # phpstan at max level
composer cs        # php-cs-fixer, dry run
composer cs:fix    # php-cs-fixer, applying changes
composer check     # all three
```

No test talks to the live API. They run against a mocked PSR-18 client and
recorded fixtures, so you need no API key and nothing is charged.

## What the tests expect

`tests/Unit/ContractTest.php` guards the shared SDK contract that the
JavaScript, Go, PHP and Rust clients all follow: the method names, one page
versus every page, how list envelopes unwrap, and that a 202 is a result
rather than an error. If you add or rename a public method, that test will
tell you, and the same change belongs in the other three clients.

## Style

`php-cs-fixer` decides formatting, so run `composer cs:fix` before you open a
pull request. Public methods and models carry docblocks; keep them accurate
rather than decorative.
