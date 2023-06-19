<?php

namespace Rammewerk\Component\Request\Flash;


use Rammewerk\Component\Request\Session;

/**
 * Flash Messages
 *
 * Store messages to the user's session and display them on the next page load.
 *
 * For example, if a requested route isn't found, we can add a message telling
 * the user that the requested route isn't found. Then we can redirect the user
 * back to a working route. If the template implements flash messages, the
 * user will get notified.
 *
 */
class Flash {

    private const FLASH_KEY = 'bonsy_flash';


    /**
     * Flash constructor.
     *
     * @param Session $session
     */
    public function __construct(
        private readonly Session $session
    ) {}


    /**
     * Success Notification
     *
     * @param string $message
     */
    public function success(string $message): void {
        $this->save(new FlashModel(FlashTypeEnum::SUCCESS, $message));
    }


    /**
     * Error Notification
     *
     * @param string $message
     */
    public function error(string $message): void {
        $this->save(new FlashModel(FlashTypeEnum::ERROR, $message));
    }


    /**
     * Information Notification
     *
     * @param string $message
     * @noinspection PhpUnused
     */
    public function info(string $message): void {
        $this->save(new FlashModel(FlashTypeEnum::INFO, $message));
    }


    /**
     * Warning Notification
     *
     * @param string $message
     */
    public function warning(string $message): void {
        $this->save(new FlashModel(FlashTypeEnum::WARNING, $message));
    }


    /**
     * Notify Messages
     *
     * @param string $message
     *
     * @noinspection PhpUnused
     */
    public function notify(string $message): void {
        $this->save(new FlashModel(FlashTypeEnum::NOTIFY, $message));
    }


    /**
     * Get Flash Messages and remove them from session
     *
     * @return FlashModel[]
     */
    public function get(): array {
        $flash = $this->getStored();
        $this->session->remove(self::FLASH_KEY);
        return $flash;
    }


    public function set(FlashModel $flashModel): void {
        $this->save($flashModel);
    }


    /**
     * Get stored flash messages from session
     *
     * @return FlashModel[]
     */
    private function getStored(): array {
        $stored = $this->session->get(self::FLASH_KEY) ?? [];
        if( !is_array($stored) ) return [];
        return array_map(static function(array $d): FlashModel {
            return new FlashModel(FlashTypeEnum::from($d['type']), $d['message']);
        }, $stored);
    }


    /**
     * Save message to session
     *
     * @param FlashModel $flash
     *
     * @return void
     */
    private function save(FlashModel $flash): void {

        $previous = $this->getStored();

        $list = [];

        foreach( $previous as $old ) {
            if( $old->type === $flash->type && $old->message === $flash->message ) return;
            $list[] = (array)$old;
        }

        $list[] = (array)$flash;

        $this->session->set(self::FLASH_KEY, $list);
    }


}