<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use OCP\ITempManager;
use RuntimeException;
use Throwable;

final class SevenZipBinary {
	public const PATH = '/usr/bin/7z';
	public const MINIMUM_VERSION = '25.01';
	private const MAX_CAPTURE_BYTES = 16777216;

	public function __construct(
		private ITempManager $tempManager,
	) {
	}

	public function isAvailable(): bool {
		$version = $this->getVersion();
		return $version !== null && version_compare($version, self::MINIMUM_VERSION, '>=');
	}

	public function getVersion(): ?string {
		if (!function_exists('proc_open') || !is_file(self::PATH) || !is_executable(self::PATH)) {
			return null;
		}

		$descriptorSpec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = @proc_open([self::PATH, 'i'], $descriptorSpec, $pipes);
		if (!is_resource($process)) {
			return null;
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$exitCode = proc_close($process);
		if ($exitCode !== 0) {
			return null;
		}

		return self::parseVersion((string)$stdout . "\n" . (string)$stderr);
	}

	/** @param list<string> $arguments */
	public function run(array $arguments, ?string $workingDirectory = null): void {
		$this->assertRunnable($workingDirectory);
		$this->assertArguments($arguments);

		$descriptorSpec = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', '/dev/null', 'a'],
			2 => ['file', '/dev/null', 'a'],
		];

		$process = @proc_open(
			array_merge([self::PATH], $arguments),
			$descriptorSpec,
			$pipes,
			$workingDirectory,
			null,
			['bypass_shell' => true],
		);
		if (!is_resource($process)) {
			throw new RuntimeException('Unable to start 7-Zip');
		}

		$exitCode = proc_close($process);
		if ($exitCode !== 0) {
			throw new RuntimeException('7-Zip failed with exit code ' . $exitCode);
		}
	}

	/**
	 * Run a read-only 7-Zip command and return bounded stdout.
	 *
	 * @param list<string> $arguments
	 */
	public function capture(array $arguments, ?string $workingDirectory = null): string {
		$this->assertRunnable($workingDirectory);
		$this->assertArguments($arguments);

		$stdoutPath = $this->tempManager->getTemporaryFile('.7z.stdout');
		$stderrPath = $this->tempManager->getTemporaryFile('.7z.stderr');
		if ($stdoutPath === false || $stderrPath === false) {
			throw new RuntimeException('Unable to allocate temporary 7-Zip output files');
		}

		$descriptorSpec = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', $stdoutPath, 'w'],
			2 => ['file', $stderrPath, 'w'],
		];

		$process = @proc_open(
			array_merge([self::PATH], $arguments),
			$descriptorSpec,
			$pipes,
			$workingDirectory,
			null,
			['bypass_shell' => true],
		);
		if (!is_resource($process)) {
			throw new RuntimeException('Unable to start 7-Zip');
		}

		$exitCode = proc_close($process);
		if ($exitCode !== 0) {
			throw new RuntimeException('7-Zip inspection failed with exit code ' . $exitCode);
		}

		$size = filesize($stdoutPath);
		if ($size === false || $size > self::MAX_CAPTURE_BYTES) {
			throw new RuntimeException('7-Zip inspection output exceeds the safe limit');
		}

		$output = file_get_contents($stdoutPath);
		if ($output === false) {
			throw new RuntimeException('Unable to read 7-Zip inspection output');
		}
		return $output;
	}

	/**
	 * Extract exactly one archive entry to a caller-controlled temporary file.
	 *
	 * 7-Zip never receives a destination directory and therefore cannot create
	 * paths or links on the server. stdout is capped at the inspected size.
	 */
	public function extractEntryToFile(string $archivePath, string $entryPath, string $outputPath, int $expectedSize): void {
		$this->assertRunnable(null);
		if ($expectedSize < 0) {
			throw new RuntimeException('Invalid expected 7z entry size');
		}
		$this->assertArguments([$archivePath, $entryPath, $outputPath]);

		$descriptorSpec = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['pipe', 'w'],
			2 => ['file', '/dev/null', 'a'],
		];
		$command = [
			self::PATH,
			'x',
			'-so',
			'-bd',
			'-bb0',
			'-spd',
			'--',
			$archivePath,
			$entryPath,
		];
		$process = @proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
		if (!is_resource($process)) {
			throw new RuntimeException('Unable to start 7-Zip entry extraction');
		}

		$output = @fopen($outputPath, 'wb');
		if ($output === false) {
			fclose($pipes[1]);
			@proc_terminate($process);
			@proc_close($process);
			throw new RuntimeException('Unable to open temporary 7z entry output');
		}

		$total = 0;
		try {
			while (!feof($pipes[1])) {
				$chunk = fread($pipes[1], 1048576);
				if ($chunk === false) {
					throw new RuntimeException('Unable to read 7-Zip entry output');
				}
				if ($chunk === '') {
					if (feof($pipes[1])) {
						break;
					}
					throw new RuntimeException('7-Zip entry output stalled');
				}

				$length = strlen($chunk);
				if ($length > $expectedSize - $total) {
					throw new RuntimeException('7-Zip produced more data than was inspected');
				}
				$this->writeAll($output, $chunk);
				$total += $length;
			}
		} catch (Throwable $e) {
			@proc_terminate($process);
			fclose($pipes[1]);
			fclose($output);
			@proc_close($process);
			@unlink($outputPath);
			throw $e;
		}

		fclose($pipes[1]);
		fclose($output);
		$exitCode = proc_close($process);
		if ($exitCode !== 0 || $total !== $expectedSize) {
			@unlink($outputPath);
			throw new RuntimeException('7-Zip entry extraction did not match inspected metadata');
		}
	}

	private function assertRunnable(?string $workingDirectory): void {
		if (!$this->isAvailable()) {
			throw new RuntimeException('7-Zip ' . self::MINIMUM_VERSION . ' or newer is required at ' . self::PATH);
		}
		if ($workingDirectory !== null && !is_dir($workingDirectory)) {
			throw new RuntimeException('7-Zip working directory does not exist');
		}
	}

	/** @param list<string> $arguments */
	private function assertArguments(array $arguments): void {
		foreach ($arguments as $argument) {
			if (str_contains($argument, "\0")) {
				throw new RuntimeException('Invalid NUL byte in 7-Zip argument');
			}
		}
	}

	/** @param resource $stream */
	private function writeAll($stream, string $data): void {
		$offset = 0;
		$length = strlen($data);
		while ($offset < $length) {
			$written = fwrite($stream, substr($data, $offset));
			if ($written === false || $written === 0) {
				throw new RuntimeException('Unable to write temporary 7-Zip output');
			}
			$offset += $written;
		}
	}

	public static function parseVersion(string $output): ?string {
		if (preg_match('/(?:7-Zip|7z)\s+([0-9]+(?:\.[0-9]+)+)/i', $output, $matches) !== 1) {
			return null;
		}

		return $matches[1];
	}
}
