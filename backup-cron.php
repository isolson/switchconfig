#!/usr/bin/env php
<?php
/**
 * CLI script for scheduled config backups
 *
 * Usage:
 *   php backup-cron.php [--sync] [--quiet] [--switch=<address>]
 *
 * Options:
 *   --sync     Push changes to GitHub after backup
 *   --quiet    Suppress output (for cron jobs)
 *   --switch   Backup only a specific switch by address
 *
 * Example cron entry (daily at 2am):
 *   0 2 * * * /usr/bin/php /var/www/cisco-switch-manager-gui/backup-cron.php --sync --quiet >> /var/log/cisco-switch-manager-gui-backup.log 2>&1
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
	die("This script must be run from the command line.\n");
}

// Change to script directory
chdir(__DIR__);

require_once('php/functions.php');
require_once('config.php');
require_once('php/backupoperations.php');

// Parse command line options
$options = getopt('', ['sync', 'quiet', 'switch:', 'help']);

if (isset($options['help'])) {
	echo <<<HELP
Cisco Switch Manager GUI Backup CLI

Usage: php backup-cron.php [options]

Options:
  --sync          Push changes to GitHub after backup
  --quiet         Suppress output (for cron jobs)
  --switch=ADDR   Backup only a specific switch by address
  --help          Show this help message

Example cron entry (daily at 2am):
  0 2 * * * /usr/bin/php /var/www/cisco-switch-manager-gui/backup-cron.php --sync --quiet

HELP;
	exit(0);
}

$doSync = isset($options['sync']);
$quiet = isset($options['quiet']);
$singleSwitch = $options['switch'] ?? null;

function output($message, $quiet = false) {
	if (!$quiet) {
		echo date('[Y-m-d H:i:s] ') . $message . "\n";
	}
}

// Check if feature is enabled
if (!defined('ENABLE_CONFIG_BACKUP') || !ENABLE_CONFIG_BACKUP) {
	output("ERROR: Config backup feature is disabled. Set ENABLE_CONFIG_BACKUP = true in config.php", $quiet);
	exit(1);
}

// Check for credentials (from datastore or config.php)
$hasCredentials = false;
$storedCreds = getAllCredentials();
if (!empty($storedCreds)) {
	$hasCredentials = true;
} elseif (defined('CREDENTIAL_TEMPLATES') && !empty(CREDENTIAL_TEMPLATES)) {
	$hasCredentials = true;
}

if (!$hasCredentials) {
	output("ERROR: No credentials configured. Add credentials via Settings page or CREDENTIAL_TEMPLATES in config.php", $quiet);
	exit(1);
}

output("Starting config backup...", $quiet);

$switches = getAllSwitches();
$successCount = 0;
$failCount = 0;
$results = [];

// Filter to single switch if specified
if ($singleSwitch) {
	$switches = array_filter($switches, function($s) use ($singleSwitch) {
		return $s['addr'] === $singleSwitch;
	});

	if (empty($switches)) {
		output("ERROR: Switch not found: " . $singleSwitch, $quiet);
		exit(1);
	}
}

foreach ($switches as $switch) {
	$switchAddr = $switch['addr'];
	$switchName = $switch['name'] ?? $switchAddr;

	output("Backing up: " . $switchName . " (" . $switchAddr . ")", $quiet);

	// Get credentials
	$creds = getCredentialsForSwitch($switch, false); // Don't use session in CLI
	if (!$creds) {
		output("  SKIP: No credentials configured for this switch", $quiet);
		$results[$switchAddr] = ['success' => false, 'error' => 'No credentials'];
		$failCount++;
		continue;
	}

	// Get config
	$configResult = getRunningConfig($switchAddr, $creds['username'], $creds['password']);
	if (!$configResult['success']) {
		output("  FAIL: " . $configResult['error'], $quiet);
		$results[$switchAddr] = ['success' => false, 'error' => $configResult['error']];
		$failCount++;
		continue;
	}

	// Save config
	$saveResult = saveConfigBackup($switchAddr, $switchName, $configResult['config']);
	if (!$saveResult['success']) {
		output("  FAIL: " . $saveResult['error'], $quiet);
		$results[$switchAddr] = ['success' => false, 'error' => $saveResult['error']];
		$failCount++;
		continue;
	}

	output("  OK: Saved to " . basename($saveResult['path']), $quiet);
	$results[$switchAddr] = ['success' => true, 'path' => $saveResult['path']];
	$successCount++;
}

output("", $quiet);
output("Backup complete: " . $successCount . " succeeded, " . $failCount . " failed", $quiet);

// Sync to GitHub if requested
if ($doSync) {
	output("", $quiet);
	output("Syncing to GitHub...", $quiet);

	$settings = getBackupSettings();
	if (!$settings['github_configured']) {
		output("  SKIP: GitHub not configured", $quiet);
	} else {
		$syncResult = syncToGitHub("Scheduled backup - " . date('Y-m-d H:i:s'));
		if ($syncResult['success']) {
			if ($syncResult['error'] === 'No changes to commit') {
				output("  OK: No changes to sync", $quiet);
			} else {
				output("  OK: Pushed to GitHub", $quiet);
			}
		} else {
			output("  FAIL: " . $syncResult['error'], $quiet);
		}
	}
}

// Exit with appropriate code
exit($failCount > 0 ? 1 : 0);
