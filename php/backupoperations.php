<?php

require_once('functions.php');

/**
 * Get credentials for a switch (from datastore, template, or session)
 *
 * @param array $switch Switch config array
 * @param bool $useSession Whether to fallback to session credentials
 * @return array|false ['username' => ..., 'password' => ...] or false
 */
function getCredentialsForSwitch($switch, $useSession = true) {
	// First, try to get credentials from the datastore
	$datastoreCreds = getCredentialsForSwitchFromDatastore($switch);
	if ($datastoreCreds !== null) {
		return $datastoreCreds;
	}

	// Check if switch has a credential template assigned in config.php
	if (isset($switch['credential']) && defined('CREDENTIAL_TEMPLATES')) {
		foreach (CREDENTIAL_TEMPLATES as $template) {
			if ($template['id'] === $switch['credential']) {
				return [
					'username' => $template['username'],
					'password' => $template['password']
				];
			}
		}
	}

	// Fallback to first credential template if available
	if (defined('CREDENTIAL_TEMPLATES') && !empty(CREDENTIAL_TEMPLATES)) {
		$first = CREDENTIAL_TEMPLATES[0];
		return [
			'username' => $first['username'],
			'password' => $first['password']
		];
	}

	// Fallback to session credentials
	if ($useSession && isset($_SESSION['username']) && isset($_SESSION['password'])) {
		return [
			'username' => $_SESSION['username'],
			'password' => $_SESSION['password']
		];
	}

	return false;
}

/**
 * Get running config from a switch
 *
 * @param string $switchAddr Switch IP/hostname
 * @param string $username SSH username
 * @param string $password SSH password
 * @return array ['success' => bool, 'config' => string, 'error' => string]
 */
function getRunningConfig($switchAddr, $username, $password) {
	$connection = @ssh2_connect($switchAddr, 22);

	if ($connection === false) {
		return ['success' => false, 'config' => '', 'error' => 'Connection failed'];
	}

	if (@ssh2_auth_password($connection, $username, $password) === false) {
		return ['success' => false, 'config' => '', 'error' => 'Authentication failed'];
	}

	// Use shell mode to handle terminal paging
	$stream = @ssh2_shell($connection, 'vanilla', null, 200, 10000, SSH2_TERM_UNIT_CHARS);

	if ($stream === false) {
		return ['success' => false, 'config' => '', 'error' => 'Failed to open shell'];
	}

	// Disable paging and get running config
	$commands = "terminal length 0\n" .
	            "show running-config\n" .
	            "exit\n";

	fwrite($stream, $commands);
	stream_set_blocking($stream, true);

	// Read output with timeout
	$output = '';
	$startTime = time();
	$timeout = 60; // 60 second timeout

	while (!feof($stream) && (time() - $startTime) < $timeout) {
		$chunk = fread($stream, 8192);
		if ($chunk === false || $chunk === '') {
			usleep(100000); // 100ms
			continue;
		}
		$output .= $chunk;

		// Check if we got the full config (ends with 'end' followed by prompt)
		if (preg_match('/\nend\s*\n.*[#>]\s*$/s', $output)) {
			break;
		}
	}

	fclose($stream);

	// Clean up the output - extract just the config
	$config = cleanRunningConfig($output);

	if (empty(trim($config))) {
		return ['success' => false, 'config' => '', 'error' => 'Empty config received'];
	}

	return ['success' => true, 'config' => $config, 'error' => ''];
}

/**
 * Clean up raw SSH output to extract just the running config
 *
 * @param string $raw Raw SSH output
 * @return string Cleaned config
 */
function cleanRunningConfig($raw) {
	$lines = explode("\n", $raw);
	$config = [];
	$inConfig = false;

	foreach ($lines as $line) {
		// Remove carriage returns
		$line = str_replace("\r", '', $line);

		// Start capturing at "Current configuration" or "Building configuration"
		if (preg_match('/^(Current configuration|Building configuration)/', $line)) {
			$inConfig = true;
		}

		if ($inConfig) {
			$config[] = $line;
		}

		// Stop at "end" line
		if ($inConfig && trim($line) === 'end') {
			break;
		}
	}

	return implode("\n", $config);
}

/**
 * Save config to backup directory
 *
 * @param string $switchAddr Switch identifier
 * @param string $switchName Switch display name
 * @param string $config Config content
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function saveConfigBackup($switchAddr, $switchName, $config) {
	$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';

	// Create safe directory name from switch address
	$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $switchAddr);
	$switchDir = $backupDir . '/configs/' . $safeName;

	// Create directories if needed
	if (!is_dir($switchDir)) {
		if (!@mkdir($switchDir, 0755, true)) {
			return ['success' => false, 'path' => '', 'error' => 'Failed to create backup directory: ' . $switchDir];
		}
	}

	// Generate filename with timestamp
	$timestamp = date('Y-m-d_H-i-s');
	$filename = $timestamp . '_running-config.txt';
	$filepath = $switchDir . '/' . $filename;

	// Add metadata header
	$header = "! Backup created: " . date('Y-m-d H:i:s') . "\n" .
	          "! Switch: " . $switchName . "\n" .
	          "! Address: " . $switchAddr . "\n" .
	          "!\n";

	// Write config
	if (@file_put_contents($filepath, $header . $config) === false) {
		return ['success' => false, 'path' => '', 'error' => 'Failed to write config file'];
	}

	// Cleanup old backups if retention is set
	cleanupOldBackups($switchDir);

	return ['success' => true, 'path' => $filepath, 'error' => ''];
}

/**
 * Remove old backup files based on retention policy
 *
 * @param string $switchDir Directory containing backups for a switch
 */
function cleanupOldBackups($switchDir) {
	$retention = defined('BACKUP_RETENTION_COUNT') ? BACKUP_RETENTION_COUNT : 30;

	if ($retention <= 0) {
		return; // Unlimited retention
	}

	$files = glob($switchDir . '/*_running-config.txt');
	if (count($files) <= $retention) {
		return;
	}

	// Sort by modification time (oldest first)
	usort($files, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});

	// Remove oldest files
	$toRemove = count($files) - $retention;
	for ($i = 0; $i < $toRemove; $i++) {
		@unlink($files[$i]);
	}
}

/**
 * Get backup settings from JSON file
 *
 * @return array Settings array
 */
function getBackupSettings() {
	$settingsFile = defined('BACKUP_SETTINGS_FILE')
		? BACKUP_SETTINGS_FILE
		: '/var/lib/cisco-switch-manager-gui/backup_settings.json';

	if (!file_exists($settingsFile)) {
		return [
			'github_configured' => false,
			'github_repo' => '',
			'github_token' => '',
			'github_branch' => 'main',
			'auto_sync' => false,
			'last_sync' => null
		];
	}

	$content = @file_get_contents($settingsFile);
	if ($content === false) {
		return ['github_configured' => false];
	}

	$settings = json_decode($content, true);
	return is_array($settings) ? $settings : ['github_configured' => false];
}

/**
 * Save backup settings to JSON file
 *
 * @param array $settings Settings to save
 * @return bool Success
 */
function saveBackupSettings($settings) {
	$settingsFile = defined('BACKUP_SETTINGS_FILE')
		? BACKUP_SETTINGS_FILE
		: '/var/lib/cisco-switch-manager-gui/backup_settings.json';

	// Ensure directory exists
	$dir = dirname($settingsFile);
	if (!is_dir($dir)) {
		if (!@mkdir($dir, 0755, true)) {
			return false;
		}
	}

	return @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Initialize git repository in backup directory
 *
 * @return array ['success' => bool, 'error' => string]
 */
function initGitRepo() {
	$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';

	if (!is_dir($backupDir)) {
		if (!@mkdir($backupDir, 0755, true)) {
			return ['success' => false, 'error' => 'Failed to create backup directory'];
		}
	}

	// Check if already a git repo
	if (is_dir($backupDir . '/.git')) {
		return ['success' => true, 'error' => ''];
	}

	// Initialize git repo
	$output = [];
	$returnCode = 0;
	exec('cd ' . escapeshellarg($backupDir) . ' && git init 2>&1', $output, $returnCode);

	if ($returnCode !== 0) {
		return ['success' => false, 'error' => 'Git init failed: ' . implode("\n", $output)];
	}

	// Create .gitignore
	$gitignore = "# Ignore settings file with sensitive data\nbackup_settings.json\n";
	@file_put_contents($backupDir . '/.gitignore', $gitignore);

	return ['success' => true, 'error' => ''];
}

/**
 * Configure git remote for GitHub
 *
 * @param string $repoUrl GitHub repository URL
 * @param string $token GitHub personal access token
 * @return array ['success' => bool, 'error' => string]
 */
function configureGitRemote($repoUrl, $token) {
	$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';

	// Parse repo URL to inject token
	$parsedUrl = parse_url($repoUrl);
	if (!$parsedUrl || !isset($parsedUrl['host'])) {
		return ['success' => false, 'error' => 'Invalid repository URL'];
	}

	// Build authenticated URL
	$authUrl = 'https://' . $token . '@' . $parsedUrl['host'] . $parsedUrl['path'];
	if (!str_ends_with($authUrl, '.git')) {
		$authUrl .= '.git';
	}

	$output = [];
	$returnCode = 0;

	// Remove existing remote if present
	exec('cd ' . escapeshellarg($backupDir) . ' && git remote remove origin 2>&1', $output, $returnCode);

	// Add remote
	exec('cd ' . escapeshellarg($backupDir) . ' && git remote add origin ' . escapeshellarg($authUrl) . ' 2>&1', $output, $returnCode);

	if ($returnCode !== 0) {
		return ['success' => false, 'error' => 'Failed to configure remote: ' . implode("\n", $output)];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Commit and push changes to GitHub
 *
 * @param string $message Commit message
 * @return array ['success' => bool, 'error' => string]
 */
function syncToGitHub($message = null) {
	$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';
	$settings = getBackupSettings();

	if (!$settings['github_configured']) {
		return ['success' => false, 'error' => 'GitHub not configured'];
	}

	if ($message === null) {
		$message = 'Config backup - ' . date('Y-m-d H:i:s');
	}

	$output = [];
	$returnCode = 0;

	// Configure git user if not set
	exec('cd ' . escapeshellarg($backupDir) . ' && git config user.email "cisco-switch-manager-gui@localhost" 2>&1', $output, $returnCode);
	exec('cd ' . escapeshellarg($backupDir) . ' && git config user.name "Cisco Switch Manager GUI Backup" 2>&1', $output, $returnCode);

	// Add all changes
	exec('cd ' . escapeshellarg($backupDir) . ' && git add -A 2>&1', $output, $returnCode);

	// Check if there are changes to commit
	exec('cd ' . escapeshellarg($backupDir) . ' && git status --porcelain 2>&1', $output, $returnCode);
	if (empty(trim(implode('', $output)))) {
		return ['success' => true, 'error' => 'No changes to commit'];
	}

	// Commit
	$output = [];
	exec('cd ' . escapeshellarg($backupDir) . ' && git commit -m ' . escapeshellarg($message) . ' 2>&1', $output, $returnCode);

	if ($returnCode !== 0 && !str_contains(implode('', $output), 'nothing to commit')) {
		return ['success' => false, 'error' => 'Commit failed: ' . implode("\n", $output)];
	}

	// Push
	$branch = $settings['github_branch'] ?? 'main';
	$output = [];
	exec('cd ' . escapeshellarg($backupDir) . ' && git push -u origin ' . escapeshellarg($branch) . ' 2>&1', $output, $returnCode);

	if ($returnCode !== 0) {
		// Try to pull and merge first
		exec('cd ' . escapeshellarg($backupDir) . ' && git pull origin ' . escapeshellarg($branch) . ' --rebase 2>&1', $output, $returnCode);
		exec('cd ' . escapeshellarg($backupDir) . ' && git push -u origin ' . escapeshellarg($branch) . ' 2>&1', $output, $returnCode);

		if ($returnCode !== 0) {
			return ['success' => false, 'error' => 'Push failed: ' . implode("\n", $output)];
		}
	}

	// Update last sync time
	$settings['last_sync'] = date('Y-m-d H:i:s');
	saveBackupSettings($settings);

	return ['success' => true, 'error' => ''];
}

/**
 * Get list of backups for a switch
 *
 * @param string $switchAddr Switch identifier
 * @return array List of backup files with metadata
 */
function getBackupsForSwitch($switchAddr) {
	$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';
	$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $switchAddr);
	$switchDir = $backupDir . '/configs/' . $safeName;

	if (!is_dir($switchDir)) {
		return [];
	}

	$files = glob($switchDir . '/*_running-config.txt');
	$backups = [];

	foreach ($files as $file) {
		$basename = basename($file);
		$timestamp = str_replace('_running-config.txt', '', $basename);
		$backups[] = [
			'file' => $basename,
			'path' => $file,
			'timestamp' => $timestamp,
			'date' => date('Y-m-d H:i:s', filemtime($file)),
			'size' => filesize($file)
		];
	}

	// Sort by date descending (newest first)
	usort($backups, function($a, $b) {
		return filemtime($b['path']) - filemtime($a['path']);
	});

	return $backups;
}

/**
 * Get the latest backup for a switch
 *
 * @param string $switchAddr Switch identifier
 * @return array|null Backup info or null
 */
function getLatestBackup($switchAddr) {
	$backups = getBackupsForSwitch($switchAddr);
	return !empty($backups) ? $backups[0] : null;
}

/**
 * Compare two config files
 *
 * @param string $file1 Path to first config
 * @param string $file2 Path to second config
 * @return array Diff lines
 */
function compareConfigs($file1, $file2) {
	if (!file_exists($file1) || !file_exists($file2)) {
		return [];
	}

	$output = [];
	exec('diff -u ' . escapeshellarg($file1) . ' ' . escapeshellarg($file2) . ' 2>&1', $output);

	return $output;
}

/**
 * Run backup for all switches
 *
 * @param callable|null $progressCallback Optional callback for progress updates
 * @return array Results for each switch
 */
function backupAllSwitches($progressCallback = null) {
	$switches = getAllSwitches();
	$results = [];
	$total = count($switches);
	$current = 0;

	foreach ($switches as $switch) {
		$current++;
		$switchAddr = $switch['addr'];
		$switchName = $switch['name'] ?? $switchAddr;

		if ($progressCallback) {
			$progressCallback($current, $total, $switchAddr, 'starting');
		}

		// Get credentials
		$creds = getCredentialsForSwitch($switch, true);
		if (!$creds) {
			$results[$switchAddr] = [
				'success' => false,
				'error' => 'No credentials available'
			];
			if ($progressCallback) {
				$progressCallback($current, $total, $switchAddr, 'failed');
			}
			continue;
		}

		// Get config
		$configResult = getRunningConfig($switchAddr, $creds['username'], $creds['password']);
		if (!$configResult['success']) {
			$results[$switchAddr] = [
				'success' => false,
				'error' => $configResult['error']
			];
			if ($progressCallback) {
				$progressCallback($current, $total, $switchAddr, 'failed');
			}
			continue;
		}

		// Save config
		$saveResult = saveConfigBackup($switchAddr, $switchName, $configResult['config']);
		$results[$switchAddr] = $saveResult;

		if ($progressCallback) {
			$progressCallback($current, $total, $switchAddr, $saveResult['success'] ? 'success' : 'failed');
		}
	}

	return $results;
}
