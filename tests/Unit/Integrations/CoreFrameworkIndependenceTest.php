<?php

namespace Tests\Unit\Integrations;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CoreFrameworkIndependenceTest extends TestCase
{
    public function test_core_files_do_not_import_framework_or_sdk_dependencies(): void
    {
        $coreDir = $this->integrationsCoreDirectory();

        $this->assertDirectoryExists($coreDir, 'Integrations Core directory not found.');

        $forbiddenNamespaces = [
            'Illuminate\\',
            'Laravel\\',
            'Carbon\\',
            'GuzzleHttp\\',
            'Google\\',
            'Symfony\\',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($coreDir, FilesystemIterator::SKIP_DOTS)
        );

        $phpFilesScanned = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $phpFilesScanned++;
            $content = file_get_contents($file->getPathname());

            preg_match_all('/^use\s+([^;]+);/m', $content, $matches);

            foreach ($matches[1] as $import) {
                foreach ($forbiddenNamespaces as $namespace) {
                    $this->assertStringNotContainsString(
                        $namespace,
                        $import,
                        sprintf(
                            'Forbidden dependency "%s" imported in %s.',
                            $namespace,
                            $file->getPathname()
                        )
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $phpFilesScanned, 'No Core PHP files were scanned.');
    }

    private function integrationsCoreDirectory(): string
    {
        return dirname(__DIR__, 3)
            .DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'Modules'
            .DIRECTORY_SEPARATOR.'Integrations'
            .DIRECTORY_SEPARATOR.'Core';
    }
}
