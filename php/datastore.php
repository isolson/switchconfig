<?php

/**
 * Datastore - JSON file read/write with file locking
 *
 * Provides safe concurrent access to JSON data files stored in
 * /var/lib/cisco-switch-manager-gui/data/
 */

define('DATA_DIR', '/var/lib/cisco-switch-manager-gui/data');

/**
 * Ensure the data directory exists
 *
 * @return bool Success
 */
function ensureDataDir() {
	if (!is_dir(DATA_DIR)) {
		if (!@mkdir(DATA_DIR, 0755, true)) {
			return false;
		}
	}
	return true;
}

/**
 * Get the full path for a data file
 *
 * @param string $filename Filename (e.g., 'users.json')
 * @return string Full path
 */
function getDataPath($filename) {
	return DATA_DIR . '/' . $filename;
}

/**
 * Read JSON data from a file with shared lock
 *
 * @param string $filename Filename to read
 * @param array $default Default value if file doesn't exist
 * @return array Data from file or default
 */
function readJsonFile($filename, $default = []) {
	$filepath = getDataPath($filename);

	if (!file_exists($filepath)) {
		return $default;
	}

	$handle = @fopen($filepath, 'r');
	if ($handle === false) {
		return $default;
	}

	// Acquire shared lock for reading
	if (!flock($handle, LOCK_SH)) {
		fclose($handle);
		return $default;
	}

	$content = '';
	while (!feof($handle)) {
		$content .= fread($handle, 8192);
	}

	flock($handle, LOCK_UN);
	fclose($handle);

	$data = json_decode($content, true);
	return is_array($data) ? $data : $default;
}

/**
 * Write JSON data to a file with exclusive lock
 *
 * @param string $filename Filename to write
 * @param array $data Data to write
 * @return bool Success
 */
function writeJsonFile($filename, $data) {
	if (!ensureDataDir()) {
		return false;
	}

	$filepath = getDataPath($filename);

	$handle = @fopen($filepath, 'c');
	if ($handle === false) {
		return false;
	}

	// Acquire exclusive lock for writing
	if (!flock($handle, LOCK_EX)) {
		fclose($handle);
		return false;
	}

	// Truncate and write
	ftruncate($handle, 0);
	rewind($handle);

	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	$result = fwrite($handle, $json);

	fflush($handle);
	flock($handle, LOCK_UN);
	fclose($handle);

	return $result !== false;
}

/**
 * Check if the datastore has been initialized (users exist)
 *
 * @return bool True if initialized
 */
function isDatastoreInitialized() {
	$users = readJsonFile('users.json', []);
	return !empty($users);
}

/**
 * Get all users from the datastore
 *
 * @return array Users array
 */
function getUsers() {
	return readJsonFile('users.json', []);
}

/**
 * Save users to the datastore
 *
 * @param array $users Users array
 * @return bool Success
 */
function saveUsers($users) {
	return writeJsonFile('users.json', $users);
}

/**
 * Get all switches from the datastore
 *
 * @return array Switches array
 */
function getStoredSwitches() {
	return readJsonFile('switches.json', []);
}

/**
 * Save switches to the datastore
 *
 * @param array $switches Switches array
 * @return bool Success
 */
function saveSwitches($switches) {
	return writeJsonFile('switches.json', $switches);
}

/**
 * Get all credentials from the datastore
 *
 * @return array Credentials array (encrypted)
 */
function getStoredCredentials() {
	return readJsonFile('credentials.json', []);
}

/**
 * Save credentials to the datastore
 *
 * @param array $credentials Credentials array (encrypted)
 * @return bool Success
 */
function saveCredentials($credentials) {
	return writeJsonFile('credentials.json', $credentials);
}

/**
 * Generate a unique ID
 *
 * @param string $prefix Optional prefix
 * @return string Unique ID
 */
function generateId($prefix = '') {
	return $prefix . bin2hex(random_bytes(8));
}
