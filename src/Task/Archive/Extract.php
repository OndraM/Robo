<?php

namespace Robo\Task\Archive;

use Robo\Result;
use Robo\Task\BaseTask;

/**
 * Extracts an archive.
 *
 * Note that often, distributions are packaged in tar or zip archives
 * where the topmost folder may contain variable information, such as
 * the release date, or the version of the package.  This information
 * is very useful when unpacking by hand, but arbitrarily-named directories
 * are much less useful to scripts.  Therefore, by default, Extract will
 * remove the top-level directory, and instead store all extracted files
 * into the directory specified by $archivePath.
 *
 * To keep the top-level directory when extracting, use
 * `preserveTopDirectory(true)`.
 *
 * ``` php
 * <?php
 * $this->taskExtract($archivePath)
 *  ->to($destination)
 *  ->preserveTopDirectory(false) // the default
 *  ->run();
 * ?>
 * ```
 *
 * @method to(string) location to store extracted files
 */
class Extract extends BaseTask
{
    use \Robo\Common\DynamicParams;
    use \Robo\Common\Timer;
    use \Robo\Common\PHPStatus;

    protected $filename;
    protected $to;
    private $preserveTopDirectory = false;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function run()
    {
        if (!file_exists($this->filename)) {
            $this->printTaskError("File {$this->filename} does not exist");

            return false;
        }
        if (!($mimetype = static::archiveType($this->filename))) {
            $this->printTaskError("Could not determine type of archive for {$this->filename}");

            return false;
        }

        // We will first extract to $extractLocation and then move to $this->to
        $extractLocation = static::getTmpDir();
        @mkdir($extractLocation);
        @mkdir(dirname($this->to));

        $this->startTimer();

        $this->printTaskInfo("Extracting <info>{$this->filename}</info>");
        // Perform the extraction of a zip file.
        if (($mimetype == 'application/zip') || ($mimetype == 'application/x-zip')) {
            $result = $this->extractZip($extractLocation);
        } else {
            // Otherwise we have a possibly-compressed Tar file.
            $result = $this->extractTar($extractLocation);
        }
        if ($result->wasSuccessful()) {
            $this->printTaskInfo("<info>{$this->filename}</info> extracted");
            // Now, we want to move the extracted files to $this->to. There
            // are two possibilities that we must consider:
            //
            // (1) Archived files were encapsulated in a folder with an arbitrary name
            // (2) There was no encapsulating folder, and all the files in the archive
            //     were extracted into $extractLocation
            //
            // In the case of (1), we want to move and rename the encapsulating folder
            // to $this->to.
            //
            // In the case of (2), we will just move and rename $extractLocation.
            $filesInExtractLocation = glob("$extractLocation/*");
            $hasEncapsulatingFolder = ((count($filesInExtractLocation) == 1) && is_dir($filesInExtractLocation[0]));
            if ($hasEncapsulatingFolder && !$this->preserveTopDirectory) {
                rename($filesInExtractLocation[0], $this->to);
                rmdir($extractLocation);
            } else {
                rename($extractLocation, $this->to);
            }
        }
        $this->stopTimer();

        return $result->copy(['time' => $this->getExecutionTime()]);
    }

    protected function extractZip($extractLocation)
    {
        $result = $this->checkExtension('zip extracter', 'zlib');
        if (!$result->wasSuccessful()) {
            return $result;
        }

        $zip = new \ZipArchive();
        if (($status = $zip->open($this->filename)) !== true) {
            return Result::error($this, "Could not open zip archive {$this->filename}");
        }
        if (!$zip->extractTo($extractLocation)) {
            return Result::error($this, "Could not extract zip archive {$this->filename}");
        }
        $zip->close();

        return Result::success($this);
    }

    protected function extractTar($extractLocation)
    {
        $tar_object = new \Archive_Tar($this->filename);
        if (!$tar_object->extract($extractLocation)) {
            return Result::error($this, "Could not extract tar archive {$this->filename}");
        }

        return Result::success($this);
    }

    protected static function archiveType($filename)
    {
        $content_type = false;
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $content_type = $finfo->file($filename);
            // If finfo cannot determine the content type, then we will try other methods
            if ($content_type == 'application/octet-stream') {
                $content_type = false;
            }
        }
        // Examing the file's magic header bytes.
        if (!$content_type) {
            if ($file = fopen($filename, 'rb')) {
                $first = fread($file, 2);
                fclose($file);
                if ($first !== false) {
                    // Interpret the two bytes as a little endian 16-bit unsigned int.
                    $data = unpack('v', $first);
                    switch ($data[1]) {
                        case 0x8b1f:
                            // First two bytes of gzip files are 0x1f, 0x8b (little-endian).
                            // See http://www.gzip.org/zlib/rfc-gzip.html#header-trailer
                            $content_type = 'application/x-gzip';
                            break;

                        case 0x4b50:
                            // First two bytes of zip files are 0x50, 0x4b ('PK') (little-endian).
                            // See http://en.wikipedia.org/wiki/Zip_(file_format)#File_headers
                            $content_type = 'application/zip';
                            break;

                        case 0x5a42:
                            // First two bytes of bzip2 files are 0x5a, 0x42 ('BZ') (big-endian).
                            // See http://en.wikipedia.org/wiki/Bzip2#File_format
                            $content_type = 'application/x-bzip2';
                            break;
                    }
                }
            }
        }
        // 3. Lastly if above methods didn't work, try to guess the mime type from
        // the file extension. This is useful if the file has no identificable magic
        // header bytes (for example tarballs).
        if (!$content_type) {
            // Remove querystring from the filename, if present.
            $filename = basename(current(explode('?', $filename, 2)));
            $extension_mimetype = array(
                '.tar.gz' => 'application/x-gzip',
                '.tgz' => 'application/x-gzip',
                '.tar' => 'application/x-tar',
            );
            foreach ($extension_mimetype as $extension => $ct) {
                if (substr($filename, -strlen($extension)) === $extension) {
                    $content_type = $ct;
                    break;
                }
            }
        }

        return $content_type;
    }

    protected static function getTmpDir()
    {
        return getcwd().'/tmp'.rand().time();
    }
}