<?php

namespace Rammewerk\Component\Request;

use Exception;
use RuntimeException;
use const PHP_SESSION_ACTIVE;

class Session {

    private const CSRF_TOKEN_NAME = '_csrf_token';

    private bool $started = false;

    /** @var array<string, mixed> $session */
    private array $session;


    /**
     * @param array<string, mixed>|null $session
     */
    public function __construct(?array $session) {
        /** Prevent client-side JavaScript from accessing the session cookie.*/
        ini_set( 'session.cookie_httponly', 1 );
        $this->start();
        $this->session = $session ?? $_SESSION;
        if( !$this->has( self::CSRF_TOKEN_NAME ) ) {
            $this->regenerateCsrfToken();
        }
        $this->close();
    }


    /**
     * Start Session
     *
     * @return bool
     */
    public function start(): bool {

        if( $this->started ) return true;

        if( PHP_SESSION_ACTIVE === session_status() ) {
            throw new RuntimeException( 'Failed to start the session: already started by PHP.' );
        }

        if( headers_sent( $file, $line ) && filter_var( ini_get( 'session.use_cookies' ), FILTER_VALIDATE_BOOLEAN ) ) {
            throw new RuntimeException( sprintf( 'Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line ) );
        }

        if( !session_start() ) {
            throw new RuntimeException( 'Failed to start the session' );
        }

        $this->started = true;

        return true;

    }


    public function set(string $key, mixed $value = null): void {
        $this->start();
        $_SESSION[$key] = $this->session[$key] = $value;
        $this->close();
    }


    public function get(string $key, mixed $default = null): mixed {
        return $this->session[$key] ?? $default;
    }


    public function all(): array {
        return $this->session;
    }


    public function remove(string $key): void {
        $this->start();
        unset( $this->session[$key], $_SESSION[$key] );
        $this->close();
    }


    private function name(): string {
        return session_name() ?: '';
    }


    private function flush(): void {
        $this->session = [];
        $_SESSION = [];
    }


    /**
     * Destroy Session
     *
     * @return void
     */
    public function destroy(): void {

        # Make sure session is started
        $this->start();

        # Unset all session values
        $this->flush();

        # Get session parameters
        $params = session_get_cookie_params();

        # Delete the actual cookie.
        setcookie( $this->name(), '', time() - 86400, $params['path'], $params['domain'], $params['secure'], $params['httponly'] );

        # Destroy session
        session_unset();
        session_destroy();
        session_write_close();

    }


    /**
     * Regenerate Session ID
     *
     * @param bool $delete_old_session
     *
     * @return bool
     */
    public function regenerate(bool $delete_old_session): bool {

        $this->start();

        // Cannot regenerate the session ID for non-active sessions.
        if( PHP_SESSION_ACTIVE !== session_status() ) return false;

        if( headers_sent() ) return false;

        session_regenerate_id( $delete_old_session );

        $this->regenerateCsrfToken();

        return true;

    }


    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool {
        return !is_null( $this->get( $key ) );
    }


    /**
     * Get the CSRF token value.
     *
     * @return string|null
     */
    public function csrf_token(): ?string {
        return $this->get( self::CSRF_TOKEN_NAME );
    }


    /**
     * Regenerate the CSRF token value.
     *
     * @return void
     */
    public function regenerateCsrfToken(): void {
        try {
            $_SESSION[self::CSRF_TOKEN_NAME] = $this->session[self::CSRF_TOKEN_NAME] = bin2hex( random_bytes( 32 ) );
        } catch( Exception ) {
            unset( $_SESSION[self::CSRF_TOKEN_NAME], $this->session[self::CSRF_TOKEN_NAME] );
        }
    }


    public function close(): void {
        if( $this->started ) {
            session_write_close();
            $this->started = false;
        }
    }


}