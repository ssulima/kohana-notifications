<?php defined('SYSPATH') or die('No direct script access.');

class Task_Notifications extends Minion_Task
{
    /**
     * @var array Maps call argument values to functions of this object
     */
    protected static $actions = array(
        'process' => 'process',
        'process_mail_queue' => 'process_mail_queue',
        'cleaning' => 'cleaning',
    );

    /**
     * @var bool Turns 'dry-run' mode on when TRUE. In dry-run mode there are no changes
     *           made in the database and no mails are being actually sent
     */
    protected $dry_run = false;

    /**
     * @var array Allow '--dry-run' argument to be valid
     */
    protected $_options = array(
        'dry-run' => null,
    );

    /**
     * @param array $params
     * @return null
     */
    protected function _execute(array $params)
    {
        // Determine if '--dry-run' argument was given
        $options = Minion_CLI::options();
        if (array_key_exists('dry-run', $options)) {
            $this->dry_run = true;
        }

        $action = Arr::get($params, 1);

        if (array_key_exists($action, static::$actions) and is_callable(array($this, $action))) {
            call_user_func(array($this, $action));
            return;
        }

        // Display help in any other case
        /* @var View $view */
        $view = View::factory('minion/notifications/help')
            ->bind('actions', static::$actions);

        echo $view;
    }

    /**
     * Process notifications from the queue
     */
    protected function process()
    {
        $notification = Notification::instance();
        $notification->log((Minion_CLI::color(date('Y-m-d H:i:s'), 'cyan') . ': ' . Minion_CLI::color('Sending notifications task executed', 'white')));
        $notification->process($this->dry_run);
        $notification->log('Task terminated');
    }

    /**
     * Actually send notifications collected using 'Mail' System (Notification_System_Mail)
     */
    protected function process_mail_queue()
    {
        $mail_system = new Notification_System_Mail();
        $mail_system->process_mail_queue($this->dry_run);
    }

    protected function cleaning()
    {
        Notification::instance()->clean($this->dry_run);

        $mail_system = new Notification_System_Mail();
        $mail_system->clean_mail_queue($this->dry_run);
    }
}
