<?php

if (!function_exists('sodium_crypto_secretbox_keygen')) {
    include(LIB_DIR.'sodium-compat.phar');
}

function createEncryptedToken($purpose,$data) {
    // Try and get the encryption key from the database
    $encodedKey = systemData($purpose.'EncryptionKey');
    if (empty($encodedKey)) {
        $key = sodium_crypto_secretbox_keygen();
        systemData($purpose.'EncryptionKey',base64_encode($key));
    } else {
        $key = base64_decode($encodedKey);
    }

    $plaintext = json_encode($data);

    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

    $encryptedData = base64URLEncode($nonce . $ciphertext);

    return $encryptedData;
}

# set the purpose to "getErrors" to retrieve previous error
function decryptEncryptedToken($purpose,$encryptedData=null) {
    static $lastError=false;

    if ($purpose=='getErrors') return $lastError;
    $lastError = false;

    if (empty($encryptedData)){ $lastError='The '.$purpose.' token was not supplied'; return false; }

    $binaryData = @base64URLDecode($encryptedData);
    if (empty($binaryData)) { $lastError='The '.$purpose.' token could not be decrypted'; return false; }

    $key = @base64_decode(systemData($purpose.'EncryptionKey'));
    if (empty($key)) { $lastError='Couldn\'t find the '.$purpose.' token decryption key'; return false; }

    $nonce = substr($binaryData, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($binaryData, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    if (strlen($nonce)!=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { $lastError='Invalid token'; return false; }
    if (strlen($ciphertext)<5) { $lastError='Invalid token'; return false; }

    $plaintext = @sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

    if (empty($plaintext)) { $lastError='The '.$purpose.' token was not valid'; return false; }

    $data = @json_decode($plaintext,true);
    if (empty($data)) { $lastError='The '.$purpose.' token was empty'; return false; }

    if (isset($data['validUntil']) && $data['validUntil']<time()) { $lastError='The '.$purpose.' supplied is no longer valid'; return false; }
    if (isset($data['validAfter']) && $data['validAfter']>time()) { $lastError='The '.$purpose.' supplied is not valid yet'; return false; }

    global $USER_ID;
    if (isset($data['validUserId']) && $data['validUserId']!=$USER_ID) { $lastError='The '.$purpose.' supplied does not belong to you'; return false; }

    return $data;
}

