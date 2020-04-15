# Gateway (پکیج درگاه‌های بانکی ایران)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/Hpez/gateway.svg?style=flat-square)](https://packagist.org/packages/hpez/gateway)
[![Total Downloads](https://poser.pugx.org/hpez/gateway/downloads)](https://packagist.org/packages/hpez/gateway)


Available Banks:
 1. MELLAT
 2. SADAD (MELLI)
 3. SAMAN
 4. PARSIAN
 5. PASARGAD
 6. ZARINPAL
 7. PAYPAL
 8. ASAN PARDAKHT
 9. PAY.IR
10. SADERAT
11. IRANKISH
 
## Install
 
### Step 1:

```bash
composer require hpez/gateway
```

### Step 2:

```bash
php artisan vendor:publish --provider="Hpez\Gateway\GatewayServiceProvider"
```
 
### Step 3:

Find the config file at config/gateway.php and change it according to your needs.

## Usage example:

### Redirecting to gateway:
```php
$gateway = \Gateway::make(new \Hpez\Gateway\Mellat\Mellat());
$gateway->setCallback("your callback route");
$gateway->price($invoiceTotal)->ready();
$refId = $gateway->refId(); // شماره ارجاع بانک
$transactionId = $gateway->transactionId(); // شماره تراکنش
return $gateway->redirect();
```
### Settling the transaction:
```php
$gateway = \Gateway::verify();
$trackingCode = $gateway->trackingCode();
$refId = $gateway->refId();
$cardNumber = $gateway->cardNumber();
```
