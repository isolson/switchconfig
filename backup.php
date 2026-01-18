<?php

require_once('php/session.php');
require_once('php/functions.php');
require_once('config.php');
require_once('php/backupoperations.php');
require_once('lang.php');

// Check if feature is enabled
if (!defined('ENABLE_CONFIG_BACKUP') || !ENABLE_CONFIG_BACKUP) {
	die('Config backup feature is disabled. Set ENABLE_CONFIG_BACKUP = true in config.php');
}

$settings = getBackupSettings();
$info = "";
$infoClass = "";
$showSetup = !$settings['github_configured'];
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : ($showSetup ? 'setup' : 'backup');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// GitHub Setup
	if (isset($_POST['action']) && $_POST['action'] === 'setup_github') {
		$repoUrl = trim($_POST['github_repo'] ?? '');
		$token = trim($_POST['github_token'] ?? '');
		$branch = trim($_POST['github_branch'] ?? 'main');

		if (empty($repoUrl) || empty($token)) {
			$info = "Repository URL and token are required.";
			$infoClass = "warn";
		} else {
			// Initialize git repo
			$initResult = initGitRepo();
			if (!$initResult['success']) {
				$info = "Failed to initialize git: " . $initResult['error'];
				$infoClass = "error";
			} else {
				// Configure remote
				$remoteResult = configureGitRemote($repoUrl, $token);
				if (!$remoteResult['success']) {
					$info = "Failed to configure GitHub: " . $remoteResult['error'];
					$infoClass = "error";
				} else {
					// Save settings
					$settings['github_configured'] = true;
					$settings['github_repo'] = $repoUrl;
					$settings['github_token'] = $token;
					$settings['github_branch'] = $branch;
					$settings['auto_sync'] = isset($_POST['auto_sync']);

					if (saveBackupSettings($settings)) {
						$info = "GitHub configured successfully!";
						$infoClass = "ok";
						$showSetup = false;
						$activeTab = 'backup';
					} else {
						$info = "Failed to save settings.";
						$infoClass = "error";
					}
				}
			}
		}
	}

	// Single switch backup
	if (isset($_POST['action']) && $_POST['action'] === 'backup_switch') {
		$switchAddr = $_POST['switch_addr'] ?? '';
		$switch = getSwitchByAddr($switchAddr);

		if ($switch) {
			$creds = getCredentialsForSwitch($switch, true);
			if ($creds) {
				$result = getRunningConfig($switchAddr, $creds['username'], $creds['password']);
				if ($result['success']) {
					$saveResult = saveConfigBackup($switchAddr, $switch['name'], $result['config']);
					if ($saveResult['success']) {
						$info = "Backup completed for " . htmlspecialchars($switch['name']);
						$infoClass = "ok";

						// Auto-sync if enabled
						if ($settings['github_configured'] && $settings['auto_sync']) {
							$syncResult = syncToGitHub("Backup " . $switch['name']);
							if (!$syncResult['success'] && $syncResult['error'] !== 'No changes to commit') {
								$info .= " (sync failed: " . $syncResult['error'] . ")";
							}
						}
					} else {
						$info = "Backup failed: " . $saveResult['error'];
						$infoClass = "error";
					}
				} else {
					$info = "Failed to get config: " . $result['error'];
					$infoClass = "error";
				}
			} else {
				$info = "No credentials available for this switch.";
				$infoClass = "error";
			}
		}
	}

	// Manual GitHub sync
	if (isset($_POST['action']) && $_POST['action'] === 'sync_github') {
		$syncResult = syncToGitHub();
		if ($syncResult['success']) {
			$info = $syncResult['error'] === 'No changes to commit'
				? "No changes to sync."
				: "Synced to GitHub successfully!";
			$infoClass = "ok";
			$settings = getBackupSettings(); // Reload to get updated last_sync
		} else {
			$info = "Sync failed: " . $syncResult['error'];
			$infoClass = "error";
		}
	}

	// Reset GitHub config
	if (isset($_POST['action']) && $_POST['action'] === 'reset_github') {
		$settings['github_configured'] = false;
		$settings['github_repo'] = '';
		$settings['github_token'] = '';
		saveBackupSettings($settings);
		$showSetup = true;
		$activeTab = 'setup';
		$info = "GitHub configuration reset.";
		$infoClass = "ok";
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo translate('Config Backup', false); ?> - <?php echo translate('Cisco Switch Manager GUI', false); ?></title>
	<?php require('head.inc.php'); ?>
	<style>
	.backup-status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.9em; }
	.status-ok { background: #d4edda; color: #155724; }
	.status-error { background: #f8d7da; color: #721c24; }
	.status-pending { background: #fff3cd; color: #856404; }
	.status-none { background: #e9ecef; color: #495057; }
	.switch-list { width: 100%; border-collapse: collapse; margin: 15px 0; }
	.switch-list th, .switch-list td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
	.switch-list th { background: #f8f9fa; }
	.switch-list tr:hover { background: #f5f5f5; }
	.tab-nav { display: flex; gap: 5px; margin-bottom: 20px; }
	.tab-nav a { padding: 10px 20px; text-decoration: none; border: 1px solid #ddd; border-bottom: none; border-radius: 5px 5px 0 0; background: #f5f5f5; color: #333; }
	.tab-nav a.active { background: white; border-bottom: 1px solid white; margin-bottom: -1px; }
	.tab-content { border: 1px solid #ddd; padding: 20px; border-radius: 0 5px 5px 5px; background: white; }
	.setup-step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
	.setup-step h3 { margin-top: 0; }
	.github-status { padding: 15px; background: #e8f5e9; border-radius: 5px; margin-bottom: 20px; }
	.github-status.not-configured { background: #fff3e0; }
	.btn-row { display: flex; gap: 10px; margin-top: 15px; }
	.progress-output { background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 400px; overflow-y: auto; margin: 15px 0; }
	.changeok { color: #4caf50; font-weight: bold; }
	.changefail { color: #f44336; font-weight: bold; }
	input[type="text"], input[type="password"], input[type="url"] { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
	label { font-weight: bold; display: block; margin-top: 10px; }
	.hint { font-size: 0.85em; color: #666; margin-top: -10px; margin-bottom: 15px; }
	</style>
</head>
<body>
	<div id='container'>
		<h1 id='title'><div id='logo'></div></h1>

		<div id='splash' class='login' style='max-width: 900px;'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgSwitch' src='img/switch.png'></img>
				</div>
				<?php echo translate('Backup switch configurations and sync to GitHub.', false); ?>
			</div>

			<?php if ($info != '') { ?>
				<div class='infobox <?php echo $infoClass; ?>'><?php echo $info; ?></div>
			<?php } ?>

			<div class="tab-nav">
				<a href="?tab=backup" class="<?php echo $activeTab === 'backup' ? 'active' : ''; ?>"><?php echo translate('Backup', false); ?></a>
				<a href="?tab=setup" class="<?php echo $activeTab === 'setup' ? 'active' : ''; ?>"><?php echo translate('GitHub Setup', false); ?></a>
				<a href="?tab=history" class="<?php echo $activeTab === 'history' ? 'active' : ''; ?>"><?php echo translate('History', false); ?></a>
			</div>

			<div class="tab-content">
				<?php if ($activeTab === 'setup') { ?>
					<!-- GitHub Setup Tab -->
					<h2><?php echo translate('GitHub Repository Setup', false); ?></h2>

					<?php if ($settings['github_configured']) { ?>
						<div class="github-status">
							<strong><?php echo translate('GitHub Connected', false); ?></strong><br>
							<?php echo translate('Repository', false); ?>: <?php echo htmlspecialchars($settings['github_repo']); ?><br>
							<?php echo translate('Branch', false); ?>: <?php echo htmlspecialchars($settings['github_branch'] ?? 'main'); ?><br>
							<?php if ($settings['last_sync']) { ?>
								<?php echo translate('Last sync', false); ?>: <?php echo htmlspecialchars($settings['last_sync']); ?>
							<?php } ?>
						</div>
						<form method="POST">
							<input type="hidden" name="action" value="reset_github">
							<button type="submit" class="slubbutton destructive" onclick="return confirm('Reset GitHub configuration?');">
								<?php echo translate('Reset Configuration', false); ?>
							</button>
						</form>
					<?php } else { ?>
						<div class="setup-step">
							<h3><?php echo translate('Step 1: Create a GitHub Repository', false); ?></h3>
							<p><?php echo translate('Create a new private repository on GitHub to store your switch configs.', false); ?></p>
							<ol>
								<li><?php echo translate('Go to github.com and create a new repository', false); ?></li>
								<li><?php echo translate('Name it something like "switch-config-backups"', false); ?></li>
								<li><?php echo translate('Make it private for security', false); ?></li>
								<li><?php echo translate('Do NOT initialize with README (leave empty)', false); ?></li>
							</ol>
						</div>

						<div class="setup-step">
							<h3><?php echo translate('Step 2: Create a Personal Access Token', false); ?></h3>
							<ol>
								<li><?php echo translate('Go to GitHub Settings > Developer settings > Personal access tokens > Tokens (classic)', false); ?></li>
								<li><?php echo translate('Click "Generate new token (classic)"', false); ?></li>
								<li><?php echo translate('Give it a name like "cisco-switch-manager-gui-backup"', false); ?></li>
								<li><?php echo translate('Select scope: "repo" (full control of private repositories)', false); ?></li>
								<li><?php echo translate('Generate and copy the token (you won\'t see it again!)', false); ?></li>
							</ol>
						</div>

						<div class="setup-step">
							<h3><?php echo translate('Step 3: Configure Connection', false); ?></h3>
							<form method="POST">
								<input type="hidden" name="action" value="setup_github">

								<label for="github_repo"><?php echo translate('Repository URL', false); ?></label>
								<input type="url" name="github_repo" id="github_repo" placeholder="https://github.com/username/switch-config-backups" required>
								<p class="hint"><?php echo translate('The full URL to your GitHub repository', false); ?></p>

								<label for="github_token"><?php echo translate('Personal Access Token', false); ?></label>
								<input type="password" name="github_token" id="github_token" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" required>
								<p class="hint"><?php echo translate('Your GitHub personal access token with repo access', false); ?></p>

								<label for="github_branch"><?php echo translate('Branch Name', false); ?></label>
								<input type="text" name="github_branch" id="github_branch" value="main">
								<p class="hint"><?php echo translate('Usually "main" or "master"', false); ?></p>

								<label>
									<input type="checkbox" name="auto_sync" checked>
									<?php echo translate('Auto-sync after each backup', false); ?>
								</label>

								<div class="btn-row">
									<button type="submit" class="slubbutton"><?php echo translate('Connect to GitHub', false); ?></button>
								</div>
							</form>
						</div>
					<?php } ?>

				<?php } elseif ($activeTab === 'history') { ?>
					<!-- History Tab -->
					<h2><?php echo translate('Backup History', false); ?></h2>

					<?php
					$selectedSwitch = $_GET['history_switch'] ?? '';
					?>
					<form method="GET">
						<input type="hidden" name="tab" value="history">
						<select name="history_switch" onchange="this.form.submit();" class="fullwidth">
							<option value=""><?php echo translate('Select a switch...', false); ?></option>
							<?php foreach (getAllSwitches() as $s) { ?>
								<option value="<?php echo htmlspecialchars($s['addr']); ?>" <?php echo $selectedSwitch === $s['addr'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($s['name']); ?>
								</option>
							<?php } ?>
						</select>
					</form>

					<?php if ($selectedSwitch) {
						$backups = getBackupsForSwitch($selectedSwitch);
						if (empty($backups)) { ?>
							<p><?php echo translate('No backups found for this switch.', false); ?></p>
						<?php } else { ?>
							<table class="switch-list">
								<tr>
									<th><?php echo translate('Date', false); ?></th>
									<th><?php echo translate('Size', false); ?></th>
									<th><?php echo translate('Actions', false); ?></th>
								</tr>
								<?php foreach ($backups as $backup) { ?>
								<tr>
									<td><?php echo htmlspecialchars($backup['date']); ?></td>
									<td><?php echo number_format($backup['size'] / 1024, 1); ?> KB</td>
									<td>
										<a href="?tab=view&file=<?php echo urlencode($backup['path']); ?>" class="slubbutton secondary small">
											<?php echo translate('View', false); ?>
										</a>
									</td>
								</tr>
								<?php } ?>
							</table>
						<?php }
					} ?>

				<?php } elseif ($activeTab === 'view' && isset($_GET['file'])) { ?>
					<!-- View Config Tab -->
					<?php
					$filePath = $_GET['file'];
					$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : '/var/lib/cisco-switch-manager-gui/backups';

					// Security: ensure file is within backup directory
					$realPath = realpath($filePath);
					$realBackupDir = realpath($backupDir);

					if ($realPath && $realBackupDir && strpos($realPath, $realBackupDir) === 0) {
						$content = file_get_contents($realPath);
						?>
						<h2><?php echo translate('Config File', false); ?>: <?php echo htmlspecialchars(basename($filePath)); ?></h2>
						<a href="?tab=history" class="slubbutton secondary">&lt; <?php echo translate('Back to History', false); ?></a>
						<pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; margin-top: 15px; border-radius: 5px;"><?php echo htmlspecialchars($content); ?></pre>
						<?php
					} else {
						echo '<div class="infobox error">Invalid file path.</div>';
					}
					?>

				<?php } else { ?>
					<!-- Backup Tab -->
					<div class="github-status <?php echo $settings['github_configured'] ? '' : 'not-configured'; ?>">
						<?php if ($settings['github_configured']) { ?>
							<strong><?php echo translate('GitHub', false); ?>:</strong> <?php echo translate('Connected', false); ?>
							<?php if ($settings['last_sync']) { ?>
								| <?php echo translate('Last sync', false); ?>: <?php echo htmlspecialchars($settings['last_sync']); ?>
							<?php } ?>
							<form method="POST" style="display: inline; margin-left: 15px;">
								<input type="hidden" name="action" value="sync_github">
								<button type="submit" class="slubbutton secondary small"><?php echo translate('Sync Now', false); ?></button>
							</form>
						<?php } else { ?>
							<strong><?php echo translate('GitHub', false); ?>:</strong> <?php echo translate('Not configured', false); ?>
							- <a href="?tab=setup"><?php echo translate('Set up GitHub sync', false); ?></a>
						<?php } ?>
					</div>

					<h2><?php echo translate('Switch Backups', false); ?></h2>

					<form method="POST" id="backupAllForm">
						<input type="hidden" name="action" value="backup_all">
						<button type="button" class="slubbutton" onclick="backupAllSwitches();">
							<?php echo translate('Backup All Switches', false); ?>
						</button>
					</form>

					<div id="progressOutput" class="progress-output" style="display: none;"></div>

					<table class="switch-list">
						<tr>
							<th><?php echo translate('Switch', false); ?></th>
							<th><?php echo translate('Last Backup', false); ?></th>
							<th><?php echo translate('Actions', false); ?></th>
						</tr>
						<?php foreach (getAllSwitches() as $s) {
							$latestBackup = getLatestBackup($s['addr']);
							$statusClass = $latestBackup ? 'status-ok' : 'status-none';
							$statusText = $latestBackup ? $latestBackup['date'] : translate('No backup', false);
						?>
						<tr id="switch-row-<?php echo htmlspecialchars($s['addr']); ?>">
							<td>
								<strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
								<small><?php echo htmlspecialchars($s['addr']); ?></small>
							</td>
							<td>
								<span class="backup-status <?php echo $statusClass; ?>" id="status-<?php echo htmlspecialchars($s['addr']); ?>">
									<?php echo $statusText; ?>
								</span>
							</td>
							<td>
								<form method="POST" style="display: inline;">
									<input type="hidden" name="action" value="backup_switch">
									<input type="hidden" name="switch_addr" value="<?php echo htmlspecialchars($s['addr']); ?>">
									<button type="submit" class="slubbutton secondary small"><?php echo translate('Backup', false); ?></button>
								</form>
								<?php if ($latestBackup) { ?>
								<a href="?tab=history&history_switch=<?php echo urlencode($s['addr']); ?>" class="slubbutton secondary small">
									<?php echo translate('History', false); ?>
								</a>
								<?php } ?>
							</td>
						</tr>
						<?php } ?>
					</table>
				<?php } ?>
			</div>

			<a href='index.php'>&gt; <?php echo translate('Back', false); ?></a>
		</div>

		<?php require('foot.inc.php'); ?>
	</div>

<?php require('menu.inc.php'); ?>

<script>
function backupAllSwitches() {
	const output = document.getElementById('progressOutput');
	output.style.display = 'block';
	output.innerHTML = 'Starting backup of all switches...\n';

	fetch('backup-ajax.php?action=backup_all', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' }
	})
	.then(response => response.json())
	.then(data => {
		if (data.results) {
			for (const [addr, result] of Object.entries(data.results)) {
				const statusEl = document.getElementById('status-' + addr);
				if (result.success) {
					output.innerHTML += '<span class="changeok">OK</span> - ' + addr + '\n';
					if (statusEl) {
						statusEl.className = 'backup-status status-ok';
						statusEl.textContent = new Date().toLocaleString();
					}
				} else {
					output.innerHTML += '<span class="changefail">FAIL</span> - ' + addr + ': ' + result.error + '\n';
					if (statusEl) {
						statusEl.className = 'backup-status status-error';
					}
				}
			}
			output.innerHTML += '\nBackup complete!';
			if (data.sync_result) {
				output.innerHTML += '\nGitHub sync: ' + (data.sync_result.success ? 'OK' : 'Failed - ' + data.sync_result.error);
			}
		}
	})
	.catch(error => {
		output.innerHTML += '<span class="changefail">Error: ' + error + '</span>\n';
	});
}
</script>

</body>
</html>
