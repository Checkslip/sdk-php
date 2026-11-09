# checkslip/sdk

[English](./README.md)

ไคลเอนต์ PHP ทางการของ [Checkslip API](https://checkslip.com/docs)

Checkslip ถามรายการโอนกับธนาคารต้นทางให้ คุณส่งข้อความใน QR code บนสลิป หรือรูปสลิปมา แล้ว Checkslip คืนข้อมูลที่ธนาคารมีสำหรับรายการนั้น ได้แก่ จำนวนเงิน เวลา ผู้โอนและผู้รับ ส่วนการตัดสินใจว่าจะรับเงินนั้นหรือไม่เป็นของคุณ โดยเทียบกับออร์เดอร์ของคุณเอง

แพ็กเกจนี้ต้องใช้ PHP 8.2 ขึ้นไป และไคลเอนต์ HTTP ที่เป็น [PSR-18](https://www.php-fig.org/psr/psr-18/) ตัวใดก็ได้

## ติดตั้ง

```sh
composer require checkslip/sdk
```

ถ้าโปรเจกต์ยังไม่มีไคลเอนต์ PSR-18 ให้ติดตั้งเพิ่ม ที่นิยมคือ Guzzle

```sh
composer require checkslip/sdk guzzlehttp/guzzle
```

ไคลเอนต์จะหาตัวที่ติดตั้งไว้เองผ่าน `php-http/discovery` จึงไม่ต้องตั้งค่าอะไรเพิ่ม

## เริ่มใช้งาน

สร้าง API key ใน dashboard ของ Checkslip แล้วเก็บไว้ที่ฝั่งเซิร์ฟเวอร์

```php
use Checkslip\Client;

$checkslip = new Client(apiKey: getenv('CHECKSLIP_API_KEY'));
```

### ค้นหาจากข้อความ QR บนสลิป

อ่าน QR code บนสลิปด้วยตัวอ่าน QR อะไรก็ได้ แล้วส่งข้อความที่ได้มา

```php
$check = $checkslip->slips->lookup(
    slip: '00330006000001010306902126302105340875102TH9104F86F',
    clientReference: 'ORDER-10023', // ไม่บังคับ เป็น id ของคุณเอง
);

if ($check->isFound()) {
    echo $check->slip->amount->toBaht(), " บาท\n";
    echo 'จาก ', $check->slip->sender->nameTh, ' ธนาคาร ', $check->slip->sender->bank->code, "\n";
    echo 'ถึง ', $check->slip->receiver->nameTh, ' ', $check->slip->receiver->account, "\n";
}
```

จำนวนเงินคืนมาเป็นสตางค์ (100 สตางค์ = 1 บาท) ใช้ `toBaht()` จัดรูปแบบได้ เลขบัญชีคืนมาเฉพาะ 4 ตัวท้าย

### ค้นหาจากรูปสลิป

ส่ง `Upload` แทนข้อความ QR รับไฟล์ JPEG, PNG และ WebP ขนาดไม่เกิน 10 MB Checkslip จะอ่าน QR จากรูปให้ และไม่เก็บรูปไว้

```php
use Checkslip\Upload;

$check = $checkslip->slips->lookup(slip: Upload::fromPath('/tmp/slip.jpg'));
```

ถ้าเป็นไฟล์ที่ผู้ใช้อัปโหลดมาใน request

```php
$check = $checkslip->slips->lookup(
    slip: Upload::fromString($contents, filename: 'slip.jpg', contentType: 'image/jpeg'),
);
```

ส่งข้อความ QR หรือรูป อย่างใดอย่างหนึ่ง ไม่ใช่ทั้งคู่

### ผลลัพธ์

`$check->outcome` เป็นค่าใดค่าหนึ่งต่อไปนี้

| ผลลัพธ์      | ความหมาย                                                                                   | ใช้เครดิต |
| ----------- | ------------------------------------------------------------------------------------------ | --------- |
| `FOUND`     | ธนาคารคืนรายการโอนมา ข้อมูลอยู่ใน `$check->slip`                                              | 1         |
| `NOT_FOUND` | ธนาคารไม่มีรายการโอนที่อ้างอิงนี้                                                                | 0         |
| `DUPLICATE` | บัญชีนี้เคยค้นรายการนี้แล้ว `$check->duplicateOf` ชี้ไปที่ครั้งก่อน                                   | 0         |
| `PENDING`   | ธนาคารยังไม่ตอบ Checkslip จะถามต่อให้ และส่ง webhook `slip.checked` เมื่อได้ผล ส่วน `$check->retryAfter` บอกว่าควรรออีกกี่วินาทีก่อนอ่านซ้ำด้วย `slips->get()` | 0 |

ทั้งหมดนี้เป็นผลลัพธ์ปกติ SDK จึงคืนค่ากลับมา ส่วนปัญหาที่ตัวคำขอเอง เช่น QR ที่อ่านไม่ออก จะโยนเป็น exception

## ข้อผิดพลาด

ทุก exception สืบทอดจาก `Checkslip\Exception\CheckslipException` ซึ่งเก็บ `$status` ของ HTTP, `$code` ของปัญหา, `$requestId` ที่ใช้แจ้งทีมซัพพอร์ต และเนื้อหา problem ตาม [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) ทั้งก้อน

```php
use Checkslip\Exception\InsufficientCreditException;
use Checkslip\Exception\RateLimitException;
use Checkslip\Exception\UnprocessableEntityException;
use Checkslip\Exception\CheckslipException;

try {
    $check = $checkslip->slips->lookup(slip: $qr);
} catch (UnprocessableEntityException $e) {
    // ข้อความไม่ใช่ QR ของสลิป หรือ QR ถูกแก้ หรือสลิปเก่าเกินไป
    // $e->code เป็น INVALID_QR, QR_TAMPERED, TOO_OLD หรือ INVALID_FILE และไม่หักเครดิต
} catch (InsufficientCreditException $e) {
    // เครดิตไม่พอ ต้องเติม
} catch (RateLimitException $e) {
    echo "ลองใหม่ในอีก {$e->retryAfter} วินาที\n";
} catch (CheckslipException $e) {
    error_log("{$e->status} {$e->code} {$e->requestId} {$e->getMessage()}");
    throw $e;
}
```

| Exception                      | เกิดเมื่อ                                               |
| ------------------------------ | ------------------------------------------------------ |
| `BadRequestException`          | 400                                                    |
| `AuthenticationException`      | 401 API key หาย ผิด หรือถูกยกเลิก                        |
| `InsufficientCreditException`  | 402 `NO_CREDIT` หรือ `CREDIT_EXPIRED`                   |
| `PermissionDeniedException`    | 403 key ไม่มีสิทธิ์ที่คำขอนี้ต้องใช้                        |
| `NotFoundException`            | 404                                                    |
| `ConflictException`            | 409                                                    |
| `GoneException`                | 410 ของหายไปแล้วถาวร เช่น export ที่หมดอายุ                |
| `UnprocessableEntityException` | 422 รวมถึง 413 และ 415 เมื่ออัปโหลดไฟล์ไม่ถูกต้อง          |
| `RateLimitException`           | 429 มี `$retryAfter` เป็นวินาที                          |
| `ServerException`              | 5xx ที่ไม่ใช่ 503                                        |
| `ServiceUnavailableException`  | 503 ระบบยังอยู่ แต่ตอบไม่ได้ชั่วคราว                       |
| `ConnectionException`          | ไม่ได้รับคำตอบเลย                                        |
| `UnexpectedResponseException`  | คำตอบไม่ตรงรูปแบบที่ API ประกาศไว้                        |
| `WebhookVerificationException` | ลายเซ็นหรือเวลาของ webhook ไม่ผ่าน                        |

## การลองใหม่

ไคลเอนต์ลองใหม่ให้เองไม่เกิน `maxRetries` ครั้ง (ค่าเริ่มต้น 2) โดยรอ 0.5 วินาที แล้ว 1 วินาที แล้ว 2 วินาที เพิ่มขึ้นเรื่อย ๆ พร้อมสุ่มเวลาเล็กน้อย และลองใหม่เฉพาะกรณีที่ส่งซ้ำแล้วไม่เกิดผลสองครั้ง ได้แก่ 429 กับ 408, 5xx และการเชื่อมต่อล้มเหลวบน GET, PUT, DELETE และคำขอที่มี `Idempotency-Key` ถ้าคำตอบมี `Retry-After` มาด้วยก็จะรอตามนั้น

ปิดได้ด้วย `new Client(apiKey: $key, maxRetries: 0)`

## Idempotency

`slips->lookup()` ส่ง `Idempotency-Key` ทุกครั้ง การส่งซ้ำด้วย key เดิมจะได้ผลลัพธ์แรกกลับมาและไม่หักเครดิตรอบสอง ถ้าคุณไม่ได้ส่ง key มา SDK จะสร้างให้และใช้ซ้ำเฉพาะตอนที่มันลองใหม่เองในคำขอนั้น

ถ้าโปรเซสของคุณอาจพังแล้วรันงานเดิมซ้ำ ให้ส่ง key ที่สร้างจากข้อมูลของคุณเอง เพื่อให้รอบสองได้คำตอบของรอบแรก

```php
$checkslip->slips->lookup(slip: $qr, idempotencyKey: "order-{$order->id}-slip-1");
```

## การแบ่งหน้า

รายการทุกชุดมีเมธอดคู่กัน `listAll()` ไล่ทุกหน้าและคืนทีละรายการ โดยตาม cursor ให้เอง

```php
foreach ($checkslip->slips->listAll(outcome: [Outcome::Found]) as $record) {
    echo $record->requestId, "\n";
}
```

ถ้าต้องการทีละหน้า ให้ใช้ `list()`

```php
$page = $checkslip->slips->list(transRef: '630210534087');
foreach ($page as $record) {
    echo $record->requestId, "\n";
}
while ($page = $page->nextPage()) {
    // ...
}
```

ถ้าจะทำต่อภายหลัง ให้เก็บ `$page->nextCursor` ไว้ แล้วส่งกลับมาเป็น `cursor`

## Webhooks

Checkslip ส่งข้อความ `slip.checked` ไปที่ endpoint ของคุณเมื่อการค้นหาเสร็จ รวมถึงรายการที่เคยเป็น `PENDING` ข้อความถูกเซ็นด้วยมาตรฐาน [Standard Webhooks](https://www.standardwebhooks.com) ให้ตรวจลายเซ็นก่อนเชื่อข้อความทุกครั้ง

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

ถ้าใช้ PSR-7 server request เรียก `verifyRequest($request)` ได้เลย มันอ่าน body และ header ให้

ต้องส่ง body ดิบ ก่อนแปลง JSON · Checkslip จะส่งข้อความซ้ำถ้าไม่ได้รับคำตอบ 2xx และทุกครั้งที่ส่งซ้ำจะมี `webhook-id` เดิม จึงควรเก็บ id ที่จัดการแล้วไว้ และข้ามของซ้ำ

หลังเปลี่ยน secret ตัวเก่าใช้ได้อีก 24 ชั่วโมง และข้อความจะมีลายเซ็นสองชุด ช่วงนั้นให้ส่งทั้งสองตัว `new Webhook([$newSecret, $oldSecret])`

## ฟังก์ชันที่มี

| Resource | เมธอด |
| --- | --- |
| `$client->slips` | `lookup`, `get`, `list`, `listAll`, `counts` และ `exports` (`create`, `get`, `downloadPart`) |
| `$client->banks` | `list` |
| `$client->billing` | `balance->get`, `info` (`get`, `update`), `packages->list`, `ledger` (`list`, `listAll`), `orders` (`list`, `listAll`, `get`, `sync`), `receipts` (`list`, `listAll`, `get`) |
| `$client->usage` | `get` |
| `$client->audit` | `events` (`list`, `listAll`, `get`), `requests` (`list`, `listAll`, `get`), `exports` (`list`, `listAll`, `create`, `get`, `download`) |
| `$client->webhooks` | `verify`, `eventTypes->list`, `endpoints` (`list`, `listAll`, `create`, `get`, `update`, `delete`, `recover`, `rotateSecret`, `sendTest`), `deliveries` (`list`, `listAll`, `get`, `redeliver`) |

ชื่อชุดเดียวกันและโครงเดียวกันนี้อยู่ในไคลเอนต์ JavaScript, Go และ Rust ด้วย

การดาวน์โหลดคืนค่าเป็น `StreamInterface` ของ PSR-7 ทำให้ไฟล์ CSV หรือไฟล์หลักฐานขนาดใหญ่ไม่ต้องโหลดเข้าหน่วยความจำทั้งก้อน

## การตั้งค่า

```php
$checkslip = new Client(
    apiKey: 'csk_...',                      // ค่าเริ่มต้น: CHECKSLIP_API_KEY จาก environment
    baseUrl: 'https://api.checkslip.com',   // ค่าเริ่มต้น
    maxRetries: 2,                          // ค่าเริ่มต้น
    timeout: 30.0,                          // วินาทีต่อหนึ่งครั้ง ใช้กับไคลเอนต์ Guzzle และ Symfony
    httpClient: $myPsr18Client,             // ค่าเริ่มต้น: ค้นหาให้เอง
);
```

## ลิงก์

- เอกสาร: https://checkslip.com/docs
- API reference: https://checkslip.com/en/docs/reference
- SDK ภาษาอื่น: [JavaScript](https://github.com/Checkslip/sdk-js), [Go](https://github.com/Checkslip/sdk-go), [Rust](https://github.com/Checkslip/sdk-rust)
