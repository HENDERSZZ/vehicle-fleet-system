<?php
if (!defined('LICENSE_CRYPTO_KEY')) {
    define('LICENSE_CRYPTO_KEY', base64_decode(getenv('LICENSE_CRYPTO_KEY')));
}
if (!defined('LICENSE_HMAC_SECRET')) {
    define('LICENSE_HMAC_SECRET', base64_decode(getenv('LICENSE_HMAC_SECRET')));
}
