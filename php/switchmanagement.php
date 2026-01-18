<?php

/**
 * Switch Management - Switch and credential CRUD operations
 *
 * Manages switches and credentials stored in the datastore,
 * with fallback to config.php constants.
 */

require_once(__DIR__ . '/datastore.php');
require_once(__DIR__ . '/encryption.php');

/**
 * Get all switches merged from datastore and config.php
 *
 * @return array Merged switches array
 */
function getMergedSwitches() {
	$storedSwitches = getStoredSwitches();

	// If we have stored switches, use them
	if (!empty($storedSwitches)) {
		return $storedSwitches;
	}

	// Fall back to config.php SWITCHES constant
	if (defined('SWITCHES')) {
		return SWITCHES;
	}

	return [];
}

/**
 * Get all credentials (decrypted)
 *
 * @return array Credentials array with decrypted values
 */
function getAllCredentials() {
	$storedCreds = getStoredCredentials();
	$result = [];

	foreach ($storedCreds as $cred) {
		$decrypted = decryptCredential($cred);
		if ($decrypted !== false) {
			$result[] = [
				'id' => $cred['id'] ?? '',
				'name' => $cred['name'] ?? '',
				'username' => $decrypted['username'],
				'password' => $decrypted['password'],
				'created_at' => $cred['created_at'] ?? null,
				'updated_at' => $cred['updated_at'] ?? null
			];
		}
	}

	// Fall back to config.php CREDENTIAL_TEMPLATES if no stored credentials
	if (empty($result) && defined('CREDENTIAL_TEMPLATES')) {
		foreach (CREDENTIAL_TEMPLATES as $template) {
			$result[] = [
				'id' => $template['id'] ?? '',
				'name' => $template['name'] ?? '',
				'username' => $template['username'] ?? '',
				'password' => $template['password'] ?? '',
				'from_config' => true
			];
		}
	}

	return $result;
}

/**
 * Get credential by ID (decrypted)
 *
 * @param string $id Credential ID
 * @return array|null Credential data or null if not found
 */
function getCredentialById($id) {
	$storedCreds = getStoredCredentials();

	foreach ($storedCreds as $cred) {
		if (isset($cred['id']) && $cred['id'] === $id) {
			$decrypted = decryptCredential($cred);
			if ($decrypted !== false) {
				return [
					'id' => $cred['id'],
					'name' => $cred['name'] ?? '',
					'username' => $decrypted['username'],
					'password' => $decrypted['password'],
					'created_at' => $cred['created_at'] ?? null,
					'updated_at' => $cred['updated_at'] ?? null
				];
			}
		}
	}

	// Check config.php templates
	if (defined('CREDENTIAL_TEMPLATES')) {
		foreach (CREDENTIAL_TEMPLATES as $template) {
			if (isset($template['id']) && $template['id'] === $id) {
				return [
					'id' => $template['id'],
					'name' => $template['name'] ?? '',
					'username' => $template['username'] ?? '',
					'password' => $template['password'] ?? '',
					'from_config' => true
				];
			}
		}
	}

	return null;
}

/**
 * Create a new credential
 *
 * @param string $name Display name
 * @param string $username SSH username
 * @param string $password SSH password
 * @return array ['success' => bool, 'error' => string, 'id' => string]
 */
function createCredential($name, $username, $password) {
	$creds = getStoredCredentials();

	// Encrypt the credential
	$encrypted = encryptCredential($username, $password);
	if ($encrypted === false) {
		return ['success' => false, 'error' => 'Failed to encrypt credentials.', 'id' => null];
	}

	$id = generateId('cred_');
	$cred = [
		'id' => $id,
		'name' => $name,
		'username' => $encrypted['username'],
		'password' => $encrypted['password'],
		'created_at' => date('Y-m-d H:i:s'),
		'updated_at' => date('Y-m-d H:i:s')
	];

	$creds[] = $cred;

	if (!saveCredentials($creds)) {
		return ['success' => false, 'error' => 'Failed to save credentials.', 'id' => null];
	}

	return ['success' => true, 'error' => '', 'id' => $id];
}

/**
 * Update a credential
 *
 * @param string $id Credential ID
 * @param array $updates Fields to update (name, username, password)
 * @return array ['success' => bool, 'error' => string]
 */
function updateCredential($id, $updates) {
	$creds = getStoredCredentials();
	$found = false;

	foreach ($creds as $index => $cred) {
		if (isset($cred['id']) && $cred['id'] === $id) {
			$found = true;

			if (isset($updates['name'])) {
				$creds[$index]['name'] = $updates['name'];
			}

			// Re-encrypt if username or password changed
			if (isset($updates['username']) || isset($updates['password'])) {
				// Get current decrypted values
				$current = decryptCredential($cred);
				if ($current === false) {
					return ['success' => false, 'error' => 'Failed to decrypt existing credential.'];
				}

				$newUsername = $updates['username'] ?? $current['username'];
				$newPassword = $updates['password'] ?? $current['password'];

				$encrypted = encryptCredential($newUsername, $newPassword);
				if ($encrypted === false) {
					return ['success' => false, 'error' => 'Failed to encrypt credentials.'];
				}

				$creds[$index]['username'] = $encrypted['username'];
				$creds[$index]['password'] = $encrypted['password'];
			}

			$creds[$index]['updated_at'] = date('Y-m-d H:i:s');
			break;
		}
	}

	if (!$found) {
		return ['success' => false, 'error' => 'Credential not found.'];
	}

	if (!saveCredentials($creds)) {
		return ['success' => false, 'error' => 'Failed to save credentials.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Delete a credential
 *
 * @param string $id Credential ID
 * @return array ['success' => bool, 'error' => string]
 */
function deleteCredential($id) {
	$creds = getStoredCredentials();
	$found = false;

	$newCreds = [];
	foreach ($creds as $cred) {
		if (isset($cred['id']) && $cred['id'] === $id) {
			$found = true;
			continue;
		}
		$newCreds[] = $cred;
	}

	if (!$found) {
		return ['success' => false, 'error' => 'Credential not found.'];
	}

	// Check if credential is in use
	$switches = getStoredSwitches();
	foreach ($switches as $switch) {
		if (isset($switch['credential']) && $switch['credential'] === $id) {
			return ['success' => false, 'error' => 'Credential is in use by one or more switches.'];
		}
	}

	if (!saveCredentials($newCreds)) {
		return ['success' => false, 'error' => 'Failed to save credentials.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Get a switch by address
 *
 * @param string $addr Switch address
 * @return array|null Switch data or null if not found
 */
function getSwitchByAddress($addr) {
	$switches = getMergedSwitches();

	foreach ($switches as $switch) {
		if (isset($switch['addr']) && $switch['addr'] === $addr) {
			return $switch;
		}
	}

	return null;
}

/**
 * Create a new switch
 *
 * @param string $addr Switch address (IP or hostname)
 * @param string $name Display name
 * @param string $group Group name
 * @param string|null $credential Credential ID (optional)
 * @return array ['success' => bool, 'error' => string]
 */
function createSwitch($addr, $name, $group, $credential = null) {
	$switches = getStoredSwitches();

	// Import from config.php if this is the first switch
	if (empty($switches) && defined('SWITCHES') && !empty(SWITCHES)) {
		$switches = SWITCHES;
	}

	// Check if address already exists
	foreach ($switches as $s) {
		if (isset($s['addr']) && $s['addr'] === $addr) {
			return ['success' => false, 'error' => 'Switch with this address already exists.'];
		}
	}

	$switch = [
		'addr' => $addr,
		'name' => $name,
		'group' => $group
	];

	if ($credential !== null && $credential !== '') {
		$switch['credential'] = $credential;
	}

	$switches[] = $switch;

	if (!saveSwitches($switches)) {
		return ['success' => false, 'error' => 'Failed to save switch.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Update a switch
 *
 * @param string $addr Original switch address
 * @param array $updates Fields to update (addr, name, group, credential)
 * @return array ['success' => bool, 'error' => string]
 */
function updateSwitch($addr, $updates) {
	$switches = getStoredSwitches();

	// Import from config.php if no stored switches
	if (empty($switches) && defined('SWITCHES') && !empty(SWITCHES)) {
		$switches = SWITCHES;
	}

	$found = false;

	foreach ($switches as $index => $switch) {
		if (isset($switch['addr']) && $switch['addr'] === $addr) {
			$found = true;

			// Check if new address conflicts
			if (isset($updates['addr']) && $updates['addr'] !== $addr) {
				foreach ($switches as $s) {
					if ($s['addr'] === $updates['addr']) {
						return ['success' => false, 'error' => 'Switch with this address already exists.'];
					}
				}
				$switches[$index]['addr'] = $updates['addr'];
			}

			if (isset($updates['name'])) {
				$switches[$index]['name'] = $updates['name'];
			}

			if (isset($updates['group'])) {
				$switches[$index]['group'] = $updates['group'];
			}

			if (array_key_exists('credential', $updates)) {
				if ($updates['credential'] === null || $updates['credential'] === '') {
					unset($switches[$index]['credential']);
				} else {
					$switches[$index]['credential'] = $updates['credential'];
				}
			}

			break;
		}
	}

	if (!$found) {
		return ['success' => false, 'error' => 'Switch not found.'];
	}

	if (!saveSwitches($switches)) {
		return ['success' => false, 'error' => 'Failed to save switch.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Delete a switch
 *
 * @param string $addr Switch address
 * @return array ['success' => bool, 'error' => string]
 */
function deleteSwitch($addr) {
	$switches = getStoredSwitches();

	// Import from config.php if no stored switches
	if (empty($switches) && defined('SWITCHES') && !empty(SWITCHES)) {
		$switches = SWITCHES;
	}

	$found = false;
	$newSwitches = [];

	foreach ($switches as $switch) {
		if (isset($switch['addr']) && $switch['addr'] === $addr) {
			$found = true;
			continue;
		}
		$newSwitches[] = $switch;
	}

	if (!$found) {
		return ['success' => false, 'error' => 'Switch not found.'];
	}

	if (!saveSwitches($newSwitches)) {
		return ['success' => false, 'error' => 'Failed to save switches.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Get unique groups from all switches
 *
 * @return array List of group names
 */
function getSwitchGroups() {
	$switches = getMergedSwitches();
	$groups = [];

	foreach ($switches as $switch) {
		if (isset($switch['group']) && !in_array($switch['group'], $groups)) {
			$groups[] = $switch['group'];
		}
	}

	sort($groups);
	return $groups;
}

/**
 * Import switches from config.php to datastore
 *
 * @return array ['success' => bool, 'error' => string, 'count' => int]
 */
function importSwitchesFromConfig() {
	if (!defined('SWITCHES') || empty(SWITCHES)) {
		return ['success' => false, 'error' => 'No switches defined in config.php', 'count' => 0];
	}

	$switches = SWITCHES;

	if (!saveSwitches($switches)) {
		return ['success' => false, 'error' => 'Failed to save switches.', 'count' => 0];
	}

	return ['success' => true, 'error' => '', 'count' => count($switches)];
}

/**
 * Import credentials from config.php to datastore
 *
 * @return array ['success' => bool, 'error' => string, 'count' => int]
 */
function importCredentialsFromConfig() {
	if (!defined('CREDENTIAL_TEMPLATES') || empty(CREDENTIAL_TEMPLATES)) {
		return ['success' => false, 'error' => 'No credential templates defined in config.php', 'count' => 0];
	}

	$count = 0;
	foreach (CREDENTIAL_TEMPLATES as $template) {
		$result = createCredential(
			$template['name'] ?? $template['id'],
			$template['username'],
			$template['password']
		);
		if ($result['success']) {
			$count++;
		}
	}

	return ['success' => true, 'error' => '', 'count' => $count];
}

/**
 * Get the default switch credentials (first credential or from session)
 *
 * @return array|null ['username' => ..., 'password' => ...] or null
 */
function getDefaultSwitchCredentials() {
	$creds = getAllCredentials();

	if (!empty($creds)) {
		return [
			'username' => $creds[0]['username'],
			'password' => $creds[0]['password']
		];
	}

	// Fallback to session credentials
	if (isset($_SESSION['username']) && isset($_SESSION['password'])) {
		return [
			'username' => $_SESSION['username'],
			'password' => $_SESSION['password']
		];
	}

	return null;
}

/**
 * Get credentials for a specific switch
 *
 * @param array $switch Switch data with optional 'credential' key
 * @return array|null ['username' => ..., 'password' => ...] or null
 */
function getCredentialsForSwitchFromDatastore($switch) {
	// Check if switch has a credential reference
	if (isset($switch['credential'])) {
		$cred = getCredentialById($switch['credential']);
		if ($cred !== null) {
			return [
				'username' => $cred['username'],
				'password' => $cred['password']
			];
		}
	}

	// Fallback to default credentials
	return getDefaultSwitchCredentials();
}

/**
 * Get credentials for display (masked password)
 *
 * @return array Credentials with masked passwords
 */
function getAllCredentialsForDisplay() {
	$creds = getAllCredentials();
	$result = [];

	foreach ($creds as $cred) {
		$result[] = [
			'id' => $cred['id'],
			'name' => $cred['name'],
			'username' => $cred['username'],
			'password_masked' => str_repeat('*', min(strlen($cred['password']), 12)),
			'from_config' => $cred['from_config'] ?? false,
			'created_at' => $cred['created_at'] ?? null,
			'updated_at' => $cred['updated_at'] ?? null
		];
	}

	return $result;
}
