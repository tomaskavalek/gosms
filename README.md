# gosms – Sending SMS via gosms.cz API

Example
----------

```php
$sms = new \SMS\GoSMS("CLIENT", "SECRET");
$sms->authenticate();
$sms->setChannel(1);
$sms->setMessage('Test');
$sms->setRecipient('+420xxxyyyzzz');
$sms->setExpectedSendTime(new \DateTime('+1 hour', new \DateTimeZone('Europe/Prague')));
$sms->send();
```
