<?php /** @noinspection PhpUnused */

namespace Rammewerk\Component\Request;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Rammewerk\Component\Request\Flash\Flash;
use Rammewerk\Component\Request\File\UploadedFile;
use Rammewerk\Component\Request\Error\TokenMismatchException;

class Request {

    public Session $session;
    public Files $files;
    public Flash $flash;

    /** @var array<int|string, array<int|string, string>|string> $inputs */
    private array $inputs;
    /** @var array<string, string> $cookies */
    private array $cookies;
    /** @var array<string, string> $server */
    private array $server;



    /**
     * @param array<string, string|array<int|string, string>> $inputs
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     * @param array<string, string> $server
     * @param array<string, mixed> $session
     */
    public function __construct(
        array $inputs = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        array $session = []
    ) {
        /** @phpstan-ignore-next-line */
        $this->inputs = array_merge( $inputs, $this->getRawRequestData(), $_GET, $_POST );
        /** @phpstan-ignore-next-line */
        $this->cookies = !empty( $cookies ) ? $cookies : $_COOKIE;
        /** @phpstan-ignore-next-line */
        $this->server = !empty( $server ) ? $server : $_SERVER;
        /** @phpstan-ignore-next-line */
        $this->files = new Files( !empty( $files ) ? $files : $_FILES );
        $this->session = new Session( $session );
        $this->flash = new Flash( $this->session );
    }



    /**
     * @return array<int|string, array|string>
     */
    private function getRawRequestData(): array {
        # Reads raw data from the request body.
        $data = @file_get_contents( 'php://input' );
        # Parse query string (URL-encoded form data) into variables.
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

    /**
     * @param string $key
     *
     * @return array<int|string, string>|string|null
     */
    public function input(string $key): array|string|null {
        return $this->inputs[$key] ?? null;
    }



    /**
     * Get all post and query data
     *
     * @return array<int|string, array<int|string, string>|string|null>
     */
    public function all(): array {
        return $this->inputs;
    }



    /**
     * Get server data
     *
     * @param string $key
     *
     * @return string
     */
    public function server(string $key): string {
        return $this->server[$key] ?? '';
    }



    /**
     * Get cookie data
     *
     * @param string $key
     *
     * @return string
     */
    public function cookie(string $key): string {
        return $this->cookies[$key] ?? '';
    }



    /**
     * @param string $name
     *
     * @return UploadedFile|null
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
     * @param string $input name of input to validate
     * @param string $message
     *
     * @return void
     * @throws TokenMismatchException
     */
    public function validate_csrf(string $input = 'token', string $message = 'Unable to validate request'): void {
        $request = $this->inputString( $input ) ?? '';
        $session = $this->session->csrf_token();
        if( !empty( $session ) && hash_equals( $session, $request ) ) return;
        throw new TokenMismatchException( $message );
    }



    /*
    |--------------------------------------------------------------------------
    | Type safe inputs
    |--------------------------------------------------------------------------
    */

    public function inputString(string $key): ?string {
        $v = $this->input( $key );
        return is_string( $v ) ? $v : null;
    }



    public function inputInt(string $key): ?int {
        $v = $this->inputString( $key );
        if( !is_numeric( $v ) ) return null;
        return (int)round( (float)$v );
    }



    public function inputFloat(string $key): ?float {
        $v = $this->inputString( $key );
        if( !is_numeric( $v ) ) return null;
        return (float)$v;
    }



    public function inputBool(string $key): bool {
        $v = $this->input( $key );
        return filter_var( $v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? false;
    }



    /**
     * @param string $key
     *
     * @return array<string|int, string|array<string|int, string>>|null
     */
    public function inputArray(string $key): ?array {
        $v = $this->input( $key );
        return is_array( $v ) ? $v : null;
    }



    public function inputDateTime(string $key, ?string $format = null, bool $throwOnError = false): ?DateTimeImmutable {
        $v = $this->inputString( $key );
        if( empty( $v ) ) return null;

        try {
            $dateTime = ($format) ? DateTimeImmutable::createFromFormat( $format, $v ) : new DateTimeImmutable( $v );
            if( $dateTime === false && $throwOnError ) {
                throw new InvalidArgumentException( "Unable to parse date with the given format: $format" );
            }
            return $dateTime ?: null;
        } catch( Exception $e ) {
            if( $throwOnError ) {
                throw new InvalidArgumentException( "Unable to parse date: " . $e->getMessage() );
            }
            return null;
        }
    }



    public function inputEmail(string $string): ?string {
        $v = $this->inputString( $string );
        if( empty( $v ) ) return null;
        if( !filter_var( trim( $v ), FILTER_VALIDATE_EMAIL ) ) return null;
        return $v;
    }


}