<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Sending notifications using email
 */
abstract class Kohana_Notification_System implements Notification_Driver
{
    protected $config;

    /**
     * @var Model_Notification Single notification
     */
    protected $notification;

    /**
     * @var Notification Instance of Notification-system object (not a single message-alike notification)
     */
    protected $notification_instance;

    public function __construct()
    {
        $this->config = Kohana::$config->load('notification');
        $this->notification_instance = Notification::instance();
    }

    public function load(Model_Notification $notification)
    {
        $this->notification = $notification;

        return $this;
    }
}
