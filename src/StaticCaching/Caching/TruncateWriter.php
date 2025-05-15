<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\StaticCaching\Caching;

use Override;
use Statamic\StaticCaching\Cachers\Writer;

class TruncateWriter extends Writer
{
    private array $permissions = [
        'file'      => 0644,
        'directory' => 0755,
    ];

    #[Override]
    public function write($path, $content, $lockFor = 0)
    {
        @mkdir(dirname($path), $this->permissions['directory'], true);

        // Create the file handle. We use the "c" mode which will avoid writing an
        // empty file if we abort when checking the lock status in the next step.
        $handle = fopen($path, 'c');

        // Attempt to obtain the lock for a the file. If the file is already locked, then we'll
        // abort right here since another process is in the middle of writing the file. Since
        // file locks are only advisory, we'll have to manually check and prevent writing.
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        ftruncate($handle, 0);
        fwrite($handle, $content);
        chmod($path, $this->permissions['file']);

        // Hold the file lock for a moment to prevent other processes from trying to write the same file.
        sleep($lockFor);

        fclose($handle);

        return true;
    }
}
