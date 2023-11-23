<?php

namespace Rammewerk\Component\Request;

use Rammewerk\Component\Request\Flash\Flash;
use Rammewerk\Component\Request\File\UploadedFile;
use Rammewerk\Component\Request\Error\TokenMismatchException;

class Request {

    public Session $session;
    public Files $files;
    public Flash $flash;

    /** @var array<string, mixed> $inputs */
    private array $inputs;
    /** @var array<string, string> $cookies */
    private array $cookies;
    /** @var array<string, string> $server */
    private array $server;


    /**
     * @param array<string, mixed>|null $inputs
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $session
     */
    public function __construct(
        array $inputs = null,
        array $cookies = null,
        array $files = null,
        array $server = null,
        array $session = null
    ) {
        $this->inputs = array_merge( $inputs ?? [], $this->getRawRequestData(), $_GET, $_POST );
        $this->cookies = $cookies ?? $_COOKIE;
        $this->server = $server ?? $_SERVER;
        $this->files = new Files( $files ?? $_FILES );
        $this->session = new Session( $session );
        $this->flash = new Flash( $this->session );
    }


    private function getRawRequestData(): array {
        # Reads raw data from the request body.
        $data = @file_get_contents( 'php://input' );
        // Parse query string (URL-encoded form data) into variables.
        parse_str( $data ?: '', $parsedData );
        return $parsedData;
    }


    /*
    |--------------------------------------------------------------------------
    | Domain and URI checks
    |--------------------------------------------------------------------------
    */


    /**
     * Get Path of request without domain
     * Example '/profile/user/12'
     *
     * @return string
     */
    public function path(): string {
        return trim( parse_url( $this->server( 'REQUEST_URI' ), PHP_URL_PATH ) ?: '', '/' );
    }


    /**
     * Get root domain. Domain without subdomain:
     * Example: site.com
     *
     * @return string
     */
    public function rootDomain(): string {
        return implode( '.', array_slice( explode( '.', $this->server( 'HTTP_HOST' ) ), -2 ) );
    }


    /**
     * Get domain with subdomain
     * Example: subdomain.site.com or site.com if no subdomain
     *
     * @return string
     */
    public function domain(): string {
        return $this->server( 'HTTP_HOST' );
    }


    /**
     * Only get the subdomain.
     *
     * @return string
     */
    public function subdomain(): string {
        return implode( '.', explode( '.', $this->server( 'HTTP_HOST' ), -2 ) );
    }


    /**
     * Check if request is made over HTTPS
     *
     * @return bool
     */
    public function isHttps(): bool {
        if( $this->server( 'HTTPS' ) && $this->server( 'HTTPS' ) !== 'off' ) return true;
        if( $this->server( 'HTTP_X_FORWARDED_PROTO' ) === 'https' ) return true;
        if( $this->server( 'SERVER_PORT' ) === '443' ) return true;
        return false;
    }


    /**
     * Check if subdomain matches argument
     *
     * @param string $subdomain
     *
     * @return bool
     */
    public function isSubdomain(string $subdomain): bool {
        return strtolower( $this->subdomain() ) === strtolower( $subdomain );
    }


    /**
     * Determine if the current path matches a pattern.
     *
     * @param string ...$patterns
     *
     * @return bool
     */
    public function is(string...$patterns): bool {

        $path = rawurldecode( $this->path() );

        foreach( $patterns as $pattern ) {

            # Tru if pattern is 100% match
            if( $pattern === $path ) return true;

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $pattern = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );

            if( preg_match( '#^' . $pattern . '\z#u', $path ) === 1 ) return true;

        }

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | IP Address checks
    |--------------------------------------------------------------------------
    */

    /**
     * Get the best guess of the client's actual IP address.
     *
     * The REMOTE_ADDR is the only reliable way to get users IP address. But it can show erroneous result if the user
     * is behind a proxy server. If user is behind a proxy server, we may need to use $checkProxy to get the correct IP.
     *
     * Warning: If $checkProxy is true, the result may imply a security risk as it can be easily spoofed.
     *
     * @param bool $checkProxy
     *
     * @return string
     */
    public function getClientIp(bool $checkProxy = true): string {

        if( $checkProxy ) {

            $isValid = static function(string $ip): bool {
                if( empty( $ip ) ) return false;
                return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false;
            };

            # Whether ip is from the share internet
            if( $isValid( $this->server( 'HTTP_CLIENT_IP' ) ) ) return $this->server( 'HTTP_CLIENT_IP' );

            # Whether ip is from the proxy, the last IP is most trustworthy.
            foreach( array_reverse( explode( ",", $this->server( 'HTTP_X_FORWARDED_FOR' ) ) ) as $ip ) {
                if( $isValid( trim( $ip ) ) ) return trim( $ip );
            }

        }

        return $this->server( 'REMOTE_ADDR' );

    }


    /*
    |--------------------------------------------------------------------------
    | Get inputs, server, file and cookie data
    |--------------------------------------------------------------------------
    */

    public function input(string $key, mixed $default = null): mixed {
        return array_key_exists( $key, $this->inputs ) ? $this->inputs[$key] : $default;
    }


    /**
     * Get all post and query data
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        return $this->inputs;
    }


    /**
     * Get server data
     *
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    public function server(string $key, string $default = ''): string {
        return array_key_exists( $key, $this->server ) ? $this->server[$key] : $default;
    }


    /**
     * Get cookie data
     *
     * @param string $key
     * @param string|null $default
     *
     * @return string|null
     */
    public function cookie(string $key, string $default = null): ?string {
        return array_key_exists( $key, $this->cookies ) ? $this->cookies[$key] : $default;
    }


    /**
     * @param string $name
     *
     * @return \Core\Request\File\UploadedFile|null
     */
    public function file(string $name): ?UploadedFile {
        $file = $this->files->get( $name );
        return $file instanceof UploadedFile ? $file : null;
    }




    /*
    |--------------------------------------------------------------------------
    | CSRF check
    |--------------------------------------------------------------------------
    */


    /**
     * Validate CSRF token - Throw error if not valid
     *
     * @param string|null $page
     * @param string|null $message
     *
     * @return void
     * @throws \Core\Request\Error\TokenMismatchException
     */
    public function validate_csrf(string $page = null, string $message = null): void {
        $request = (string)$this->input( 'token' );
        $session = $page ? hash_hmac( 'sha256', $page, (string)$this->session->get( '_token2' ) ) : (string)$this->session->token();
        if( !empty( $session ) && !empty( $request ) && hash_equals( $session, $request ) ) return;
        throw new TokenMismatchException( $message ?? 'Unable to validate request' );
    }


}