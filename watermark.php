<?php
declare(strict_types=1);

/**
 * Watermark Automation Script
 *
 * Scans for PDF files, compares with previous history,
 * watermarks new files, runs Nextcloud OCC, and updates history and failures.
 *
 * Usage:
 *   php run_automation.php
 *   php run_automation.php populate
 */

final class WatermarkAutomation
{
    private string $basePath;
    private string $watermarkPath;
    private string $markpdfPath;
    private string $occPath;
    private string $historyPath;
    private string $failurePath;

    public function __construct(
        string $basePath,
        string $watermarkPath,
        string $markpdfPath,
        string $occPath,
        string $historyPath,
        string $failurePath
    ) {
        $this->basePath      = $basePath;
        $this->watermarkPath = $watermarkPath;
        $this->markpdfPath   = $markpdfPath;
        $this->occPath       = $occPath;
        $this->historyPath   = $historyPath;
        $this->failurePath   = $failurePath;
    }

    /**
     * Main entry point of the script.
     *
     * @param bool $populateOnly Whether to only store current file list as history
     */
    public function run(bool $populateOnly = false): void
    {
        $currentFiles = $this->scanPdfFiles($this->basePath);
        $previousFiles = $this->loadHistory();

        $newFiles     = array_diff($currentFiles, $previousFiles);
        $removedFiles = array_diff($previousFiles, $currentFiles);

        if ($populateOnly) {
            $this->saveHistory($currentFiles);
            echo "Populate mode: history updated with " . count($currentFiles) . " files.\n";
            return;
        }

        $success = [];
        $failures = [];

        foreach ($newFiles as $file) {
            $ok = $this->applyWatermarkAndScan($file);
            if ($ok) $success[] = $file;
            else $failures[] = $file;
        }

        $updatedFiles = array_values(array_diff(array_merge($previousFiles, $success), $removedFiles));
        $this->saveHistory($updatedFiles);
        $this->saveFailures($failures);

        echo "Scan complete.\n";
        echo "New files: " . count($newFiles) . "\n";
        echo "Removed files: " . count($removedFiles) . "\n";
        echo "Watermark succeeded: " . count($success) . "\n";
        if ($failures) {
            echo "Failures: " . count($failures) . " (see {$this->failurePath})\n";
        }
    }

    /**
     * Recursively find PDF files.
     *
     * @param string $baseDir Directory to scan
     * @return string[] List of normalized file paths
     */
    private function scanPdfFiles(string $baseDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (strtolower($file->getExtension()) === 'pdf') {
                $files[] = $this->normalizePath($file->getPathname());
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Normalize a file path for consistency.
     */
    private function normalizePath(string $path): string
    {
        $real = realpath($path);
        return $real !== false ? $real : rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Watermark and OCC-scan the given PDF file.
     *
     * @param string $filePath
     * @return bool True if both commands succeeded
     */
    private function applyWatermarkAndScan(string $filePath): bool
    {
        $markCmd = sprintf(
            '%s %s %s %s -c',
            escapeshellcmd($this->markpdfPath),
            escapeshellarg($filePath),
            escapeshellarg($this->watermarkPath),
            escapeshellarg($filePath)
        );
        $mark = $this->runCommand($markCmd);
        if ($mark['code'] !== 0) return false;

        [$groupId, $relativePath] = $this->extractForOcc($filePath);
        if ($groupId === null || $relativePath === null) return false;

        $occCmd = sprintf(
            'php %s groupfolders:scan --path %s %s',
            escapeshellarg($this->occPath),
            escapeshellarg($relativePath),
            escapeshellarg($groupId)
        );
        $occ = $this->runCommand($occCmd);
        return $occ['code'] === 0;
    }

    /**
     * Run a system command and return exit code and output.
     *
     * @return array{code:int, output:array}
     */
    private function runCommand(string $cmd): array
    {
        $output = [];
        exec($cmd . ' 2>&1', $output, $code);
        echo "Executed: $cmd\n";
        echo "Exit code: $code\n";
        echo "Output:\n" . implode("\n", $output) . "\n";
        return ['code' => $code, 'output' => $output];
    }

    /**
     * From ".../__groupfolders/<id>/<path>" extract the id and path for OCC.
     */
    private function extractForOcc(string $path): array
    {
        if (preg_match('/__groupfolders\/(\d+)\/(files\/)?(.*)/', $path, $matches)) {
            return [$matches[1], $matches[3]];
        }
        return [null, null];
    }

    /**
     * Load a list of previously known PDF files from history JSON.
     *
     * @return string[]
     */
    private function loadHistory(): array
    {
        if (!file_exists($this->historyPath)) return [];
        $data = json_decode(file_get_contents($this->historyPath), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save a list of PDF file paths to history JSON.
     *
     * @param string[] $files
     */
    private function saveHistory(array $files): void
    {
        file_put_contents(
            $this->historyPath,
            json_encode(array_values($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Write failed file paths to a separate log file.
     *
     * @param string[] $failures
     */
    private function saveFailures(array $failures): void
    {
        file_put_contents($this->failurePath, implode(PHP_EOL, $failures));
    }
}

// ---------------------- Run Script ----------------------

$scriptDir = __DIR__;
$automation = new WatermarkAutomation(
    '/path/to/scan',
    $scriptDir . '/watermark.png',
    $scriptDir . '/markpdf',
    '/path/to/nextcloud/occ',
    $scriptDir . '/history.json',
    $scriptDir . '/failures.log',
);

$isPopulate = isset($argv[1]) && strtolower($argv[1]) === 'populate';
$automation->run($isPopulate);
