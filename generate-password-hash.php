<?php
/* Check system requirements */
if (CRYPT_BLOWFISH !== 1)
  die('Het versleutelingsalgoritme Blowfish is niet beschikbaar vanuit PHP. Dit is vereist om een wachtwoord hash te genereren');

if (!function_exists('openssl_random_pseudo_bytes'))
  die('OpenSSL is niet beschikbaar vanuit PHP. Dit is vereist om een wachtwoord hash te genereren');

/* Generate a random salt (stored inside the hash output) using OpenSSL */
$salt = substr(
  strtr(base64_encode(openssl_random_pseudo_bytes(16)), '+', '.'),
  0, 22
);

/* Encrypt the supplied password using bcrypt
 * $2a$ = instructs crypt to use the bcrypt algorithm
 * 08$ = instructs crypt to use 8 as a work factor
 */
$password_hash = crypt($argv[1], '$2a$08$' . $salt);

echo "The password you've supplied results in the following password hash value: \n\n";
echo $password_hash . "\n\n";
echo "This hash can be stored in the per deployment configuration for a user\n";
