<?php

namespace Tests\Concerns;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Direktori repo Git sementara untuk test. File .git di Windows
 * bertanda read-only sehingga File::deleteDirectory() gagal diam-diam;
 * helper ini chmod dulu lalu hapus, dengan direktori unik per test.
 */
trait ManagesTestRepos
{
    protected function repoRoot(): string
    {
        $dir = sys_get_temp_dir().'/aios-test-'.uniqid();
        $this->clearRepoRoot($dir);

        return $dir;
    }

    protected function clearRepoRoot(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            @chmod($file->getPathname(), 0777);
        }

        File::deleteDirectory($dir);
    }
}
