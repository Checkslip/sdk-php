# checkslip/sdk

[ภาษาไทย](./README.th.md)

The official PHP client for the [Checkslip API](https://checkslip.com/docs).

Checkslip looks up a Thai bank transfer with the sending bank. You send the text of the QR code on a slip, or a photo of the slip, and Checkslip returns what the bank holds for that transfer: amount, time, sender and receiver. Whether to accept the payment is your decision, made against your own order.

The package needs PHP 8.2 or later and any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client.

## Install

```sh
composer require checkslip/sdk
```

If your project has no PSR-18 client yet, add one. Guzzle is the common choice:

```sh
composer require checkslip/sdk guzzlehttp/guzzle
```

The client finds an installed client and factories through `php-http/discovery`, so there is nothing to wire up.

## Quick start

Create an API key in the Checkslip web app and keep it on your server.

```php
use Checkslip\Client;
use Checkslip\Model\Slip;

$checkslip = new Client(apiKey: (string) getenv('CHECKSLIP_API_KEY'));
```

### Look up a slip by its QR text

Read the QR code on the slip with any QR reader and send the text.

```php
$check = $checkslip->slips->lookup(
    slip: '00330006000001010306902126302105340875102TH9104F86F',
    clientReference: 'ORDER-10023', // optional, your own id
);

if ($check->isFound() && $check->slip instanceof Slip) {
    echo $check->slip->amount->toBaht(), " THB\n";
    echo 'from ', $check->slip->sender->nameEn, ' at ', $check->slip->sender->bank->code, "\n";
    echo 'to ', $check->slip->receiver->nameEn, ' ', $check->slip->receiver->account, "\n";
}
```

Amounts come back in satang (100 satang = 1 baht); `toBaht()` formats them. Account numbers come back as their last 4 digits.

### Look up a slip from an image

Pass an `Upload` instead of the QR text. JPEG, PNG and WebP up to 10 MB are accepted. Checkslip reads the QR code from the image and does not store the image.

```php
use Checkslip\Upload;

$check = $checkslip->slips->lookup(slip: Upload::fromPath('/tmp/slip.jpg'));
```

From an uploaded file in a request:

```php
$check = $checkslip->slips->lookup(
    slip: Upload::fromString($contents, filename: 'slip.jpg', contentType: 'image/jpeg'),
);
```

Pass the QR text or an image, not both.

### Outcomes

`$check->outcome` is one of:

| Outcome     | Meaning                                                                                       | Credit used |
| ----------- | --------------------------------------------------------------------------------------------- | ----------- |
| `FOUND`     | The bank returned the transfer. Details are in `$check->slip`.                                | 1           |
| `NOT_FOUND` | The bank has no transfer with this reference.                                                  | 0           |
| `DUPLICATE` | This account already looked up this transfer. `$check->duplicateOf` points at the earlier one. | 0           |
| `PENDING`   | The bank has not answered yet. Checkslip keeps asking and sends a `slip.checked` webhook when it has the result. `$check->retryAfter` says how long to wait before reading it again with `slips->get()`. | 0 |

These are normal results, so the SDK returns them. Problems with the request itself, such as an unreadable QR code, come back as exceptions.

## Errors

Every exception extends `Checkslip\Exception\CheckslipException`, which carries the HTTP `$status`, the problem `$code`, the `$requestId` to quote to support, and the full [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem body.

```php
use Checkslip\Exception\InsufficientCreditException;
use Checkslip\Exception\RateLimitException;
use Checkslip\Exception\UnprocessableEntityException;
use Checkslip\Exception\CheckslipException;

try {
    $check = $checkslip->slips->lookup(slip: $qr);
} catch (UnprocessableEntityException $e) {
    // The text is not a slip QR code, the QR was altered, or the slip is too old.
    // $e->code is INVALID_QR, QR_TAMPERED, TOO_OLD or INVALID_FILE. No credit used.
} catch (InsufficientCreditException $e) {
    // Top up credits.
} catch (RateLimitException $e) {
    echo "retry in {$e->retryAfter} s\n";
} catch (CheckslipException $e) {
    error_log("{$e->status} {$e->code} {$e->requestId} {$e->getMessage()}");
    throw $e;
}
```

| Exception                         | When                                                              |
| --------------------------------- | ----------------------------------------------------------------- |
| `BadRequestException`             | 400                                                               |
| `AuthenticationException`         | 401, the API key is missing, wrong or revoked                     |
| `InsufficientCreditException`     | 402, `NO_CREDIT` or `CREDIT_EXPIRED`                              |
| `PermissionDeniedException`       | 403, the key lacks the scope this call needs                      |
| `NotFoundException`               | 404                                                               |
| `ConflictException`               | 409                                                               |
| `GoneException`                   | 410, the resource is gone for good, such as an expired export     |
| `UnprocessableEntityException`    | 422, and 413 or 415 for a bad upload                              |
| `RateLimitException`              | 429, with `$retryAfter` in seconds                                |
| `ServerException`                 | 5xx other than 503                                                |
| `ServiceUnavailableException`     | 503, up but temporarily unable to serve                           |
| `ConnectionException`             | no response arrived                                               |
| `UnexpectedResponseException`     | the response was not the shape the API documents                  |
| `WebhookVerificationException`    | a webhook signature or timestamp did not check out                |

## Retries

The client retries on its own, up to `maxRetries` times (default 2), waiting 0.5 s, then 1 s, then 2 s and so on with random jitter. It only retries when repeating the request cannot do anything twice: 429 and 408, 5xx and connection failures on GET, PUT and DELETE, and anything carrying an `Idempotency-Key`. When the response has `Retry-After`, the client waits that long.

Turn it off with `new Client(apiKey: $key, maxRetries: 0)`.

## Idempotency

`slips->lookup()` always sends an `Idempotency-Key`. A repeat with the same key returns the first result and never uses a second credit. If you do not pass one, the SDK generates a key and reuses it for that call's retries only.

If your process can crash and run the same job again, pass a key built from your own data so the second run gets the first result:

```php
$checkslip->slips->lookup(slip: $qr, idempotencyKey: "order-{$order->id}-slip-1");
```

## Pagination

Every list has a pair of methods. `listAll()` walks every page and yields each item, following the cursor for you:

```php
foreach ($checkslip->slips->listAll(outcome: [Outcome::Found]) as $record) {
    echo $record->requestId, "\n";
}
```

`list()` gives you one page at a time:

```php
$page = $checkslip->slips->list(transRef: '630210534087');
foreach ($page as $record) {
    echo $record->requestId, "\n";
}
while ($page = $page->nextPage()) {
    // ...
}
```

To resume later, store `$page->nextCursor` and pass it back as `cursor`.

## Webhooks

Checkslip sends a `slip.checked` message to your endpoints when a lookup finishes, including lookups that were `PENDING`. Messages are signed with the [Standard Webhooks](https://www.standardwebhooks.com) scheme. Check the signature before you trust a message:

```php
use Checkslip\Webhook\Webhook;
use Checkslip\Exception\WebhookVerificationException;

$webhook = new Webhook(getenv('CHECKSLIP_WEBHOOK_SECRET'));

try {
    $event = $webhook->verify($rawRequestBody, $request->getHeaders());
    if ($event->type === EventType::SlipChecked) {
        $data = $event->slipChecked();
        // $data->requestId, $data->outcome, $data->clientReference
    }
    http_response_code(204);
} catch (WebhookVerificationException $e) {
    http_response_code(400);
}
```

With a PSR-7 server request, `verifyRequest($request)` reads the body and headers for you.

Pass the raw body, before any JSON decoding. Checkslip retries a message that does not get a 2xx answer, and every retry has the same `webhook-id`, so store the ids you have handled and skip repeats.

After you rotate a secret, the old one works for 24 hours and each message carries two signatures. Pass both during the switch: `new Webhook([$newSecret, $oldSecret])`.

## What the client covers

| Resource | Methods |
| --- | --- |
| `$client->slips` | `lookup`, `get`, `list`, `listAll`, `counts`, and `exports` (`create`, `get`, `downloadPart`) |
| `$client->banks` | `list` |
| `$client->billing` | `balance->get`, `info` (`get`, `update`), `packages->list`, `ledger` (`list`, `listAll`), `orders` (`list`, `listAll`, `get`, `sync`), `receipts` (`list`, `listAll`, `get`) |
| `$client->usage` | `get` |
| `$client->audit` | `events` (`list`, `listAll`, `get`), `requests` (`list`, `listAll`, `get`), `exports` (`list`, `listAll`, `create`, `get`, `download`) |
| `$client->webhooks` | `verify`, `eventTypes->list`, `endpoints` (`list`, `listAll`, `create`, `get`, `update`, `delete`, `recover`, `rotateSecret`, `sendTest`), `deliveries` (`list`, `listAll`, `get`, `redeliver`) |

The same names, in the same shape, are in the JavaScript, Go and Rust clients.

Downloads return a PSR-7 `StreamInterface`, so a large CSV or evidence file does not have to fit in memory.

## Configuration

```php
$checkslip = new Client(
    apiKey: 'csk_...',                      // default: CHECKSLIP_API_KEY in the environment
    baseUrl: 'https://api.checkslip.com',   // default
    maxRetries: 2,                          // default
    timeout: 30.0,                          // seconds per attempt, applied to Guzzle and Symfony clients
    httpClient: $myPsr18Client,             // default: discovered
);
```

## Links

- Documentation: https://checkslip.com/docs
- API reference: https://checkslip.com/en/docs/reference
- Other SDKs: [JavaScript](https://github.com/Checkslip/sdk-js), [Go](https://github.com/Checkslip/sdk-go), [Rust](https://github.com/Checkslip/sdk-rust)
