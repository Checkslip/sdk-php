# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[semantic versioning](https://semver.org).

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
