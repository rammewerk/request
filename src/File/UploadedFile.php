<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Rammewerk\Component\Request\File;

use Exception;
use SplFileInfo;
use Rammewerk\Component\Request\Error\FileException;
use Rammewerk\Component\Request\Error\NoFileException;
use Rammewerk\Component\Request\Error\IniSizeFileException;
use Rammewerk\Component\Request\Error\PartialFileException;
use Rammewerk\Component\Request\Error\FileNotFoundException;
use Rammewerk\Component\Request\Error\FormSizeFileException;
use Rammewerk\Component\Request\Error\NoTmpDirFileException;
use Rammewerk\Component\Request\Error\ExtensionFileException;
use Rammewerk\Component\Request\Error\CannotWriteFileException;

class UploadedFile extends SplFileInfo {

    private string $originalName;
    private ?string $mimeType;
    private int $error;



    /**
     * Accepts the information of the uploaded file as provided by the PHP global $_FILES.
     *
     * The file object is only created when the uploaded file is valid (i.e. when the
     * isValid() method returns true). Otherwise, the only methods that could be called
     * on an UploadedFile instance are:
     *
     *   * getClientOriginalName,
     *   * getClientMimeType,
     *   * isValid,
     *   * getError.
     *
     * Calling any other method on a non-valid instance will cause an unpredictable result.
     *
     * @param string $path         The full temporary path to the file
     * @param string $originalName The original file name of the uploaded file
     * @param string $mimeType     The type of the file as provided by PHP; null defaults to application/octet-stream
     * @param int|null $error      The error constant of the upload (one of PHPs UPLOAD_ERR_XXX constants); null defaults to UPLOAD_ERR_OK
     *
     * @throws Exception
     */
    public function __construct(string $path, string $originalName, string $mimeType = '', ?int $error = null) {
        $this->originalName = $this->getName( $originalName );
        $this->mimeType = $mimeType ?: 'application/octet-stream';
        $this->error = $error ?? \UPLOAD_ERR_OK;

        if( $this->error === \UPLOAD_ERR_OK && !is_file( $path ) ) {
            throw new FileNotFoundException( $path );
        }

        parent::__construct( $path );

    }



    /**
     * Returns locale independent base name of the given path.
     *
     * @param string $name The new file name
     *
     * @return string containing
     */
    private function getName(string $name): string {
        $name = str_replace( '\\', '/', $name );
        $pos = strrpos( $name, '/' );
        return $pos === false ? $name : substr( $name, $pos + 1 );
    }



    /**
     * Returns the original file name.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string|null The original name
     */
    public function getClientOriginalName(): ?string {
        return $this->originalName;
    }



    /**
     * Returns the original file extension.
     *
     * It is extracted from the original file name that was uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string The extension
     * @noinspection PhpUnused
     */
    public function getClientOriginalExtension(): string {
        return pathinfo( $this->originalName, PATHINFO_EXTENSION );
    }



    /**
     * Returns the file mime type.
     *
     * The client mime type is extracted from the request from which the file
     * was uploaded, so it should not be considered as a safe value.
     *
     * For a trusted mime type, use getMimeType() instead (which guesses the mime
     * type based on the file content).
     *
     * @return string|null The mime type
     *
     * @see          getMimeType()
     * @noinspection PhpUnused
     */
    public function getClientMimeType(): ?string {
        return $this->mimeType;
    }



    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise, one of the other UPLOAD_ERR_XXX constants is returned.
     *
     * @return int The upload error
     * @noinspection PhpUnused
     */
    public function getError(): int {
        return $this->error;
    }



    /**
     * Returns whether the file was uploaded successfully.
     *
     * @return bool True if the file has been uploaded with HTTP and no error occurred
     * @noinspection PhpUnused
     */
    public function isValid(): bool {
        return $this->error === \UPLOAD_ERR_OK && is_uploaded_file( $this->getPathname() );
    }



    /**
     * Moves the file to a new location.
     *
     * @param string $directory The destination folder
     * @param string|null $name The new file name
     *
     * @return SplFileInfo
     * @noinspection PhpUnused
     */
    public function move(string $directory, ?string $name = null): SplFileInfo {

        if( $this->isValid() ) {

            $target = $this->getTargetFile( $directory, $name );

            set_error_handler( static function(int $type, string $msg) use (&$error) {
                $error = $msg;
                return true;
            } );

            $moved = move_uploaded_file( $this->getPathname(), $target );

            restore_error_handler();

            if( !$moved ) {
                throw new FileException( sprintf( 'Could not move the file "%s" to "%s" (%s)', $this->getPathname(), $target, strip_tags( $error ?? '' ) ) );
            }

            @chmod( $target, 0666 & ~umask() );

            return $target;
        }


        switch( $this->error ) {
            case \UPLOAD_ERR_INI_SIZE:
                throw new IniSizeFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_FORM_SIZE:
                throw new FormSizeFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_PARTIAL:
                throw new PartialFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_NO_FILE:
                throw new NoFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_CANT_WRITE:
                throw new CannotWriteFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_NO_TMP_DIR:
                throw new NoTmpDirFileException( $this->getErrorMessage() );
            case \UPLOAD_ERR_EXTENSION:
                throw new ExtensionFileException( $this->getErrorMessage() );
        }

        throw new FileException( $this->getErrorMessage() );

    }



    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * @return int The maximum size of an uploaded file in bytes
     */
    public static function getMaxFileSize(): int {
        $sizePostMax = self::parseFileSize( ini_get( 'post_max_size' ) );
        $sizeUploadMax = self::parseFileSize( ini_get( 'upload_max_filesize' ) );
        return min( $sizePostMax ?: PHP_INT_MAX, $sizeUploadMax ?: PHP_INT_MAX );
    }



    /**
     * Returns the given size from an ini value in bytes.
     *
     * @param string|false $size
     *
     * @return int The given size in bytes
     */
    private static function parseFileSize(string|false $size): int {

        if( $size === '' || $size === false ) return 0;

        $size = strtolower( $size );

        $max = ltrim( $size, '+' );
        if( str_starts_with( $max, '0x' ) ) {
            $max = intval( $max, 16 );
        } else if( str_starts_with( $max, '0' ) ) {
            $max = intval( $max, 8 );
        } else {
            $max = (int)$max;
        }

        switch( substr( $size, -1 ) ) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }



    /**
     * Returns an informative upload error message.
     *
     * @return string The error message regarding the specified error code
     * @noinspection PhpUnused
     */
    public function getErrorMessage(): string {

        $message = match ($this->error) {
            \UPLOAD_ERR_INI_SIZE   => 'The file "{name}" exceeds your upload_max_filesize ini directive( limit is {size} KiB).',
            \UPLOAD_ERR_FORM_SIZE  => 'The file {name} exceeds the upload limit defined in your form.',
            \UPLOAD_ERR_PARTIAL    => 'The file {name} was only partially uploaded.',
            \UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            \UPLOAD_ERR_CANT_WRITE => 'The file {name} could not be written on disk.',
            \UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
            \UPLOAD_ERR_EXTENSION  => 'File upload was stopped by a PHP extension.',
            default                => 'The file {name} was not uploaded due to an unknown error.',
        };

        return strtr( $message, [
            '{name}' => $this->getClientOriginalName(),
            '{size}' => self::getMaxFileSize() / 1024,
        ] );

    }



    /**
     * @param string $dir
     * @param string|null $name
     *
     * @return SplFileInfo
     */
    private function getTargetFile(string $dir, ?string $name): SplFileInfo {

        if( !is_dir( $dir ) ) throw new FileException( "Unable to find the $dir directory" );
        if( !is_writable( $dir ) ) throw new FileException( "Unable to write in the $dir directory" );

        $target = rtrim( $dir, '/\\' ) . \DIRECTORY_SEPARATOR . ($name ?? $this->getBasename());

        return new SplFileInfo( $target );

    }


}
