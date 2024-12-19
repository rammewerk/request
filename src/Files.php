<?php

namespace Rammewerk\Component\Request;


use Throwable;
use InvalidArgumentException;
use Rammewerk\Component\Request\File\UploadedFile;
use const UPLOAD_ERR_NO_FILE;

class Files {

    private const array FILE_KEYS = ['error', 'name', 'size', 'tmp_name', 'type'];

    /** @var array<string, UploadedFile|array<string, UploadedFile>> $parameters */
    protected array $parameters = [];



    /**
     * @param array<string, array<string, mixed>|UploadedFile> $parameters
     *
     */
    public function __construct(array $parameters) {
        $this->set( $parameters );
    }



    /**
     * @return UploadedFile[]|File\UploadedFile[][]
     */
    public function all(): array {
        return $this->parameters;
    }



    public function get(string $key): mixed {
        return $this->parameters[$key] ?? null;
    }



    /**
     * @param array<string, array<string, mixed>|UploadedFile> $parameters
     *
     * @return void
     */
    private function set(array $parameters): void {

        foreach( $parameters as $key => $file ) {

            /** @phpstan-ignore-next-line */
            if( !is_array( $file ) && !$file instanceof UploadedFile ) {
                throw new InvalidArgumentException( 'An uploaded file must be an array or an instance of UploadedFile.' );
            }

            if( $file = $this->convertFileInformation( $file ) ) {
                $this->parameters[$key] = $file;
            }

        }

    }



    /**
     * Convert files to instance of UploadFile
     *
     * @param array<string, mixed>|UploadedFile $file
     *
     * @return array<string, UploadedFile>|UploadedFile|null
     */
    private function convertFileInformation(array|UploadedFile $file): array|UploadedFile|null {

        if( $file instanceof UploadedFile ) return $file;

        $file = $this->normalize( $file );

        # Convert single file to UploadedFile instance
        if( $this->isFileArray( $file ) ) {
            if( $file['error'] === UPLOAD_ERR_NO_FILE ) return null;
            try {
                return new UploadedFile( $file['tmp_name'], $file['name'], $file['type'], $file['error'] );
            } catch( Throwable ) {
                return null;
            }
        }

        # Handle multiple files and convert them to UploadedFile instances
        $file = array_map( function($v) {
            return $v instanceof UploadedFile || is_array( $v ) ? $this->convertFileInformation( $v ) : $v;
        }, $file );

        return array_filter( $file );

    }



    /**
     * Flattens and normalize the $_FILES array.
     *
     * The $_FILES array is not consistent. This method will fix this.
     *
     * @param array<string,mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalize(array $data): array {

        # Do not covert if the file array is not normal.
        if( !$this->isFileArray( $data ) ) return $data;

        # No need to flatten if name is not an array
        if( !is_array( $data['name'] ) ) return $data;

        $files = [];

        foreach( $data['name'] as $key => $name ) {
            $files[$key] = $this->normalize( [
                'error'     => $data['error'][$key],
                'name'      => $name,
                'type'      => $data['type'][$key],
                'tmp_name'  => $data['tmp_name'][$key],
                'size'      => $data['size'][$key],
                'full_path' => $data['full_path'][$key] ?? null
            ] );
        }

        return $files;

    }



    /**
     * Make sure data is a file array
     *
     * @param array<string, mixed> $data
     *
     * @return bool
     */
    private function isFileArray(array $data): bool {
        return array_all( self::FILE_KEYS, static fn($k) => array_key_exists( $k, $data ) );
    }


}


