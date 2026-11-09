# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[semantic versioning](https://semver.org).

## [0.1.1](https://github.com/Checkslip/sdk-php/compare/v0.1.0...v0.1.1) (2026-11-09)


### Bug Fixes

* **ci:** bump the version constant and stop CI cancelling itself ([99455d0](https://github.com/Checkslip/sdk-php/commit/99455d0d0914a220c66dd014d1feb37e2ffb30c2))
* **ci:** call publish from the release job ([748fc56](https://github.com/Checkslip/sdk-php/commit/748fc5608bba74bdcece995e9953dfb42d680a2f))
* **ci:** check the release pull request before it is merged ([412510e](https://github.com/Checkslip/sdk-php/commit/412510ea05ce21941bb7c46857ac8de595f0b8b5))
* **ci:** give the artifact step the tag it uses ([2402240](https://github.com/Checkslip/sdk-php/commit/24022400f060e43f2a4d339ce4ef7b9f9355dc81))
* **ci:** publish through the release workflow, never directly ([b6077e3](https://github.com/Checkslip/sdk-php/commit/b6077e3bab64c629aec8d7a69cd08aacabf00abc))
* **ci:** stop the publish job waiting for itself ([5128665](https://github.com/Checkslip/sdk-php/commit/5128665b855dcfc34b744b6333c6224cd21fad80))

## 0.1.0 (2026-11-09)


### ⚠ BREAKING CHANGES

* **api:** nest resources to match the shared surface

### Features

* add the Checkslip API client with typed models ([68257df](https://github.com/Checkslip/sdk-php/commit/68257df44ce01e951510e950bf645dfc4756d9ea))


### Miscellaneous Chores

* cut the first release as 0.1.0 ([5fade17](https://github.com/Checkslip/sdk-php/commit/5fade172f7674101eec36fa4e2abca1b3240aeba))
* set up the composer package and its tooling ([d03cb58](https://github.com/Checkslip/sdk-php/commit/d03cb587d97a250693075af59e6b7f42f53ab241))


### Code Refactoring

* **api:** nest resources to match the shared surface ([19be258](https://github.com/Checkslip/sdk-php/commit/19be2583e42f89dda78ead061bcda5c7f5f0e014))

## [Unreleased]

## [0.1.0] - 2026-11-09

First release.

### Added

- `Checkslip\Client`, built on any PSR-18 HTTP client and PSR-17 factories, found by `php-http/discovery` when you do not pass them.
- Slip lookups with `$client->slips->lookup()`, from QR text or from an `Upload` of the slip image, plus lookup history (`get`, `list`, `listAll`, `counts`) and CSV exports under `$client->slips->exports`.
- Billing: `balance`, `info`, `packages`, `ledger`, `orders` and `receipts`, each with the same `list`/`listAll`/`get` shape as the other Checkslip SDKs.
- Usage, supported banks, and the audit log (activity, request records, evidence files).
- Webhook endpoints and deliveries, and `Checkslip\Webhook\Webhook` for Standard Webhooks signatures, including the two-secret window after a rotation.
- Retries with jitter on 429, 408, 5xx and connection failures, honouring `Retry-After`, and an `Idempotency-Key` on every call that needs one.
- Typed exceptions for every documented error, each carrying the full RFC 9457 problem body.

[Unreleased]: https://github.com/Checkslip/sdk-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Checkslip/sdk-php/releases/tag/v0.1.0
