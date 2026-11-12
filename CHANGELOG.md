# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[semantic versioning](https://semver.org).

## [0.6.0](https://github.com/Checkslip/sdk-php/compare/v0.5.0...v0.6.0) (2026-11-12)


### ⚠ BREAKING CHANGES

* ACCOUNT_RESTORED used to read back as the string 'ACCOUNT_RESTORED', because the enum had no case for it. It now reads back as CreditReason::AccountRestored, so code that compared the field with the string stops matching it.

### Features

* a credit entry's reason can say ACCOUNT_RESTORED ([3b0ff75](https://github.com/Checkslip/sdk-php/commit/3b0ff75fd999cda4f3abeb685f6568fead6b1357))
* a credit entry's reason can say ACCOUNT_RESTORED ([9d427f2](https://github.com/Checkslip/sdk-php/commit/9d427f271185930fd2a225c916d2e4fc2c5ad5b2))

## [0.5.0](https://github.com/Checkslip/sdk-php/compare/v0.4.0...v0.5.0) (2026-11-11)


### ⚠ BREAKING CHANGES

* a STAFF endpoint used to read back as the string 'STAFF', because the enum had no case for it. It now reads back as EndpointDisabledReason::Staff, so code that compared the field with the string stops matching it.

### Features

* disabled_reason can say STAFF ([24bd11c](https://github.com/Checkslip/sdk-php/commit/24bd11c4d49ca0b45968170bc1c6a2b258cee160))


## [0.4.0](https://github.com/Checkslip/sdk-php/compare/v0.3.1...v0.4.0) (2026-11-10)


### ⚠ BREAKING CHANGES

* The $freeReason filter on Slips::list(), Slips::listAll() and SlipExports::create() takes CustomerFreeReason, the nine reasons a customer can receive, instead of FreeReason. PENDING, NO_CREDIT and HOLD_EXPIRED are not among them, so phpstan and psalm flag it; at runtime the value is still sent, and the API accepts only the nine. The reason a lookup reports back is unchanged: still FreeReason|string. Counts still take the filter and still ignore it, so nothing changes there.
* UsageCounts no longer has $freeNoCredit or $freeHoldExpired (free_no_credit and free_hold_expired in the API response). Both had been returning 0 on every account before this release, and the API has now removed them from UsageCounts. Code that reads either one gets a warning and null instead of a number, and nothing stops the program, so delete those reads before you upgrade. phpstan reports it as an undefined property. A lookup that was not charged, with no reason given, counts in $freeOther.

### Features

* the free_reason filter takes the reasons a customer receives ([f41f5f9](https://github.com/Checkslip/sdk-php/commit/f41f5f9405a011d93ebd53ac544784ebb3b90e27))
* UsageCounts no longer has $freeNoCredit or $freeHoldExpired ([f772ea1](https://github.com/Checkslip/sdk-php/commit/f772ea1db69ad501f0e831c6114c931c3edd4feb))


### Bug Fixes

* **docs:** the quick start narrows to Slip before reading the transfer ([7561eaf](https://github.com/Checkslip/sdk-php/commit/7561eafe7a48dfeb9a026318f5c05dd6705e8221))

## [0.3.1](https://github.com/Checkslip/sdk-php/compare/v0.3.0...v0.3.1) (2026-11-10)


### Bug Fixes

* **docs:** say what an exhaustive match really needs ([9a22875](https://github.com/Checkslip/sdk-php/commit/9a22875812e7de76c62caf68ddce80c9b7ae3b89))

## [0.3.0](https://github.com/Checkslip/sdk-php/compare/v0.2.0...v0.3.0) (2026-11-09)


### Features

* **audit:** take several values per request filter ([76d50ac](https://github.com/Checkslip/sdk-php/commit/76d50acc4ad9e88624ff786eb0402bbed7835959))

## [0.2.0](https://github.com/Checkslip/sdk-php/compare/v0.1.1...v0.2.0) (2026-11-09)


### ⚠ BREAKING CHANGES

* **usage:** free_no_credit and free_hold_expired are deprecated and will disappear. The API no longer counts either reason, so both are always 0 and both fields will be removed from the response. They are optional from this version, so code that reads them has to handle them being absent. The counts they used to carry are in free_other.

### Features

* **usage:** add free_other and retire two free counts ([f169ade](https://github.com/Checkslip/sdk-php/commit/f169ade04684b75c477ff36fef59252eabefb9c4))

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
