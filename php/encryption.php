<?php

/**
 * Encryption - AES-256-GCM encrypt/decrypt for credentials
 *
 * Uses AES-256-GCM for authenticated encryption of sensitive data
 * like switch credentials stored at rest.
 */

require_once(__DIR__ . '/datastore.php');

define('ENCRYPTION_CIPHER', 'aes-256-gcm');
define('ENCRYPTION_KEY_FILE', DATA_DIR . '/.encryption_key');

/**
 * Get or create the encryption key
 *
 * The key is stored in a file in the data directory.
 * This file should be protected by filesystem permissions.
 *
 * @return string|false 32-byte binary key or false on failure
 */
function getEncryptionKey() {
	// Ensure data directory exists
	if (!ensureDataDir()) {
		return false;
	}

	if (file_exists(ENCRYPTION_KEY_FILE)) {
		$key = @file_get_contents(ENCRYPTION_KEY_FILE);
		if ($key !== false && strlen($key) === 32) {
			return $key;
		}
	}

	// Generate a new key
	$key = random_bytes(32);

	// Save with restrictive permissions
	$oldUmask = umask(0077);
	$result = @file_put_contents(ENCRYPTION_KEY_FILE, $key);
	umask($oldUmask);

	if ($result === false) {
		return false;
	}

	return $key;
}

/**
 * Encrypt a string using AES-256-GCM
 *
 * @param string $plaintext Data to encrypt
 * @return string|false Base64 encoded ciphertext with IV and tag, or false on failure
 */
function encryptData($plaintext) {
	$key = getEncryptionKey();
	if ($key === false) {
		return false;
	}

	// Generate random IV (12 bytes for GCM)
	$iv = random_bytes(12);

	// Encrypt
	$tag = '';
	$ciphertext = openssl_encrypt(
		$plaintext,
		ENCRYPTION_CIPHER,
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'',
		16 // Tag length
	);

	if ($ciphertext === false) {
		return false;
	}

	// Combine IV + tag + ciphertext and base64 encode
	return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a string using AES-256-GCM
 *
 * @param string $encrypted Base64 encoded ciphertext with IV and tag
 * @return string|false Decrypted plaintext or false on failure
 */
function decryptData($encrypted) {
	$key = getEncryptionKey();
	if ($key === false) {
		return false;
	}

	$data = base64_decode($encrypted);
	if ($data === false || strlen($data) < 28) { // 12 (IV) + 16 (tag) = 28 minimum
		return false;
	}

	// Extract IV, tag, and ciphertext
	$iv = substr($data, 0, 12);
	$tag = substr($data, 12, 16);
	$ciphertext = substr($data, 28);

	// Decrypt
	$plaintext = openssl_decrypt(
		$ciphertext,
		ENCRYPTION_CIPHER,
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag
	);

	return $plaintext;
}

/**
 * Encrypt credential data (username and password)
 *
 * @param string $username Username
 * @param string $password Password
 * @return array|false Encrypted credential data or false on failure
 */
function encryptCredential($username, $password) {
	$encUsername = encryptData($username);
	$encPassword = encryptData($password);

	if ($encUsername === false || $encPassword === false) {
		return false;
	}

	return [
		'username' => $encUsername,
		'password' => $encPassword
	];
}

/**
 * Decrypt credential data
 *
 * @param array $credential Encrypted credential with 'username' and 'password'
 * @return array|false Decrypted credential data or false on failure
 */
function decryptCredential($credential) {
	if (!isset($credential['username']) || !isset($credential['password'])) {
		return false;
	}

	$username = decryptData($credential['username']);
	$password = decryptData($credential['password']);

	if ($username === false || $password === false) {
		return false;
	}

	return [
		'username' => $username,
		'password' => $password
	];
}

/**
 * Test if encryption is working properly
 *
 * @return bool True if encryption works
 */
function testEncryption() {
	$testString = 'encryption_test_' . time();

	$encrypted = encryptData($testString);
	if ($encrypted === false) {
		return false;
	}

	$decrypted = decryptData($encrypted);
	return $decrypted === $testString;
}
