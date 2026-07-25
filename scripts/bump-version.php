#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Keep all maintained release version sources synchronized.
 *
 * Usage:
 *   composer bump-version 0.2.0
 *   composer bump-version -- 0.2.0 --dry-run
 */

function fail(string $message): void
{
	fwrite(STDERR, "Error: {$message}\n");
	exit(1);
}

function write_file(string $path, string $contents): void
{
	if (false === file_put_contents($path, $contents, LOCK_EX)) {
		fail("Could not write {$path}.");
	}
}

$targetVersion = $argv[1] ?? '';
$dryRun        = in_array('--dry-run', $argv, true);

if (1 !== preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/D', $targetVersion)) {
	fail('Usage: composer bump-version X.Y.Z (preview: composer bump-version -- X.Y.Z --dry-run)');
}

$root = dirname(__DIR__);

$fields = array(
	array(
		'file'    => 'package.json',
		'label'   => 'package.json version',
		'pattern' => '/(?<before>"version"\s*:\s*")(?<version>[^"]+)(?<after>")/',
	),
	array(
		'file'    => 'composer.json',
		'label'   => 'composer.json version',
		'pattern' => '/(?<before>"version"\s*:\s*")(?<version>[^"]+)(?<after>")/',
	),
	array(
		'file'    => 'cart-rebound.php',
		'label'   => 'plugin header version',
		'pattern' => '/^(?<before>[ \t]*\*[ \t]+Version:[ \t]+)(?<version>[^\s]+)(?<after>[ \t]*)$/m',
	),
	array(
		'file'    => 'cart-rebound.php',
		'label'   => 'CART_REBOUND_VERSION',
		'pattern' => '/^(?<before>define\([ \t]*\'CART_REBOUND_VERSION\',[ \t]*\')(?<version>[^\']+)(?<after>\'[ \t]*\);)$/m',
	),
	array(
		'file'    => 'readme.txt',
		'label'   => 'readme.txt Stable tag',
		'pattern' => '/^(?<before>Stable tag:[ \t]*)(?<version>[^\s]+)(?<after>[ \t]*)$/mi',
	),
);

$originals = array();
$versions  = array();

foreach ($fields as $field) {
	$relativePath = $field['file'];
	$absolutePath = "{$root}/{$relativePath}";

	if (!array_key_exists($relativePath, $originals)) {
		$contents = file_get_contents($absolutePath);
		if (false === $contents) {
			fail("Could not read {$relativePath}.");
		}
		$originals[$relativePath] = $contents;
	}

	$matchCount = preg_match_all($field['pattern'], $originals[$relativePath], $matches);
	if (1 !== $matchCount) {
		fail("Expected exactly one {$field['label']} field in {$relativePath}.");
	}

	$currentVersion = $matches['version'][0];
	if (1 !== preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/D', $currentVersion)) {
		fail("{$field['label']} contains invalid version {$currentVersion}.");
	}

	$versions[$field['label']] = $currentVersion;
}

$outdatedVersions = array_values(
	array_unique(
		array_filter(
			array_values($versions),
			static function (string $version) use ($targetVersion): bool {
				return $version !== $targetVersion;
			}
		)
	)
);

foreach ($outdatedVersions as $outdatedVersion) {
	if (!version_compare($targetVersion, $outdatedVersion, '>')) {
		fail("Target version {$targetVersion} must be newer than every current version; found {$outdatedVersion}.");
	}
}

$updated = $originals;

foreach ($fields as $field) {
	$relativePath = $field['file'];
	$replaceCount = 0;
	$result       = preg_replace_callback(
		$field['pattern'],
		static function (array $matches) use ($targetVersion): string {
			return $matches['before'] . $targetVersion . $matches['after'];
		},
		$updated[$relativePath],
		1,
		$replaceCount
	);

	if (null === $result || 1 !== $replaceCount) {
		fail("Could not update {$field['label']}.");
	}

	$updated[$relativePath] = $result;
}

$changedFiles = array();
foreach ($updated as $relativePath => $contents) {
	if ($contents !== $originals[$relativePath]) {
		$changedFiles[] = $relativePath;
	}
}

if ($dryRun) {
	$reportedFiles = $changedFiles;
	if (in_array('composer.json', $changedFiles, true)) {
		$reportedFiles[] = 'composer.lock';
	}

	echo "Version bump dry run: {$targetVersion}\n";
	foreach ($versions as $label => $version) {
		echo "- {$label}: {$version} -> {$targetVersion}\n";
	}
	echo 'Files to update: ' . (empty($reportedFiles) ? 'none' : implode(', ', $reportedFiles)) . "\n";
	exit(0);
}

if (empty($changedFiles)) {
	echo "All maintained version fields already use {$targetVersion}.\n";
	exit(0);
}

$lockPath     = "{$root}/composer.lock";
$lockOriginal = file_get_contents($lockPath);

if (false === $lockOriginal) {
	fail('Could not back up composer.lock.');
}

foreach ($changedFiles as $relativePath) {
	write_file("{$root}/{$relativePath}", $updated[$relativePath]);
}

$command = 'composer update --lock --no-install --no-interaction --no-audit --no-scripts';
passthru($command, $composerStatus);

if (0 !== $composerStatus) {
	foreach ($originals as $relativePath => $contents) {
		write_file("{$root}/{$relativePath}", $contents);
	}
	write_file($lockPath, $lockOriginal);

	fail('Composer could not refresh composer.lock; all version files were restored.');
}

echo "Bumped Cart Rebound to {$targetVersion}.\n";
echo "Next: add '= {$targetVersion} =' under '== Changelog ==' in readme.txt.\n";
echo "Then run: bash scripts/check-release-version.sh {$targetVersion}\n";
