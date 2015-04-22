<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Notification helper.
 */
class Kohana_Notification
{
    const TYPE_ERROR            = 'D_ERROR';
    const TYPE_DEBUG            = 'D_DEBUG';
    const TYPE_EMERGENCY        = 'D_EMERG';
    const TYPE_WARNING          = 'D_WARNING';
    const TYPE_INFO             = 'D_INFO';
    const TYPE_LOGIN_FAILED     = 'LOGIN_FAILED';
    const TYPE_ACCESS_DENIED    = 'ACCESS_DENIED';

    protected static $instance;

    protected $config;

    protected $log;

    protected $database;

    protected $table_name;

    public static $type_names = array(
        self::TYPE_ERROR            => 'error',
        self::TYPE_DEBUG            => 'debug',
        self::TYPE_EMERGENCY        => 'emergency',
        self::TYPE_WARNING          => 'warning',
        self::TYPE_INFO             => 'information',
        self::TYPE_LOGIN_FAILED     => 'login failed',
        self::TYPE_ACCESS_DENIED    => 'access denied',
    );

    /**
     * Get a singleton instance of Notification object
     *
     * @return Notification
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new Notification();
        }

        return static::$instance;
    }

    /**
     * Constructor, which - among others - loads notification configuration.
     *
     * @throws Kohana_Exception
     */
    public function __construct()
    {
        if (!class_exists('Database')) {
            throw new ErrorException('Notification system requires Database module');
        }

        if (!class_exists('ORM')) {
            throw new ErrorException('Notification system requires ORM module');
        }

        if (!class_exists('Minion_Task')) {
            throw new ErrorException('Notification system requires Minion module');
        }

        $this->log = new Log();
        $this->log->attach(new Log_File(APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'notifications'));
        register_shutdown_function(array($this->log, 'write'));
        $this->config = Kohana::$config->load('notification');
        $this->database = Database::instance($this->config->get('database'));
        $this->table_name = $this->config->get('table_name');
    }

    /**
     * Enqueue notification first, it will be sent using background job
     *
     * @param int        $type
     * @param string     $description
     * @param array|null $vars Optional. Values to replace in the string-alike data
     *
     * @return bool True when notification recorded successfully, false otherwise.
     */
    public function send($type, $description, array $vars = null)
    {
        if ($vars) {
            if (is_string($description)) {
                $description = strtr($description, $vars);
            } elseif (is_array($description)) {
                array_walk_recursive(
                    $description, function (&$v, $k) use (&$vars) {
                        if (is_string($v)) {
                            $v = strtr($v, $vars);
                        }
                    }
                );
            }
        }

        $query = DB::insert(
            $this->table_name,
            array(
                'type',
                'description',
                'created',
            )
        )->values(
            array(
                $type,
                empty($description) ? null : (is_scalar($description) ? $description : json_encode($description)),
                time(),
            )
        );

        try {
            list($insert_id, $affected_rows) = $query->execute($this->database);

            if ($affected_rows < 1) {
                $message = 'Notification was not saved.';
                $this->log->add(Log::ERROR, $message);
            } else {
                return true;
            }
        } catch (Database_Exception $e) {
            $this->log->add(Log::ERROR, $e);
        }

        return false;
    }

    /**
     * Notification sending (may be different: email, RSS, others)
     * XXX: Could be modified to support partials, i.e. to not kill the script when
     *      there will be 100000 notifications waiting in the queue.
     *
     * @param bool $dry_run
     *
     * @throws Kohana_Exception
     */
    public function process($dry_run = false)
    {
        $this->log('Processing notifications queue');

        // Load all unprocessed notifications
        $notifications = ORM::factory('Notification')
            ->where('processed', 'IS', null)
            ->order_by('created');

        // Will not process all messages at once, will split them into chunks if preferred
        if ($batch_limit = $this->config->get('batch_limit')) {
            $notifications->limit($batch_limit);
        }

        $notifications = $notifications->find_all();

        if (!$notifications->count()) {
            $this->log(Minion_CLI::color('No notifications to be processed, exiting.', 'yellow'));
            return;
        }

        $type_systems = $this->config->get('type_systems');

        foreach ($notifications as $notification) {
            if ($_types = (array) Arr::get($type_systems, $notification->type)) {
                foreach ($_types as $_type) {
                    $class_name = 'Notification_System_'.mb_convert_case($_type, MB_CASE_TITLE);
                    $this->log('Trying to use ' . $class_name .' as notification system...');
                    if (class_exists($class_name)) {
                        /* @var Notification_System $system */
                        $system = new $class_name();
                        $system->load($notification);

                        $system->send($dry_run);
                        if (!$dry_run) {
                            $notification->processed = time();
                            $notification->save();
                        }

                    } else {
                        $this->log(Minion_CLI::color('Can\'t find class ' . $class_name . '! Skipping...', 'red'));
                        $this->log->add(Log::ERROR, '[Notifications] Class :class_name does not exist.', array(':class_name' => $class_name));
                    }
                }
            } else {
                $this->log(Minion_CLI::color('No notification system defined for ' . $notification->type . ', skipping...', 'yellow'));
            }
        }
    }

    public function clean($dry_run = false) {
        $this->log('Preparing to clean ' . Minion_CLI::color($this->table_name, 'yellow') . ' table');
        $now = time();
        $week_ago = $now - 7 * 24 * 60 * 60;
        $this->log('All records processed before ' . Minion_CLI::color(date('Y-m-d H:i:s', $week_ago), 'yellow') . ' (a week ago) will be DELETED');
        if ($dry_run) {
            $deleted_rows = DB::select(array(DB::expr('COUNT(id)'), 'count'))
                ->from($this->table_name)
                ->where('processed', 'IS NOT', null)
                ->where('processed', '<', $week_ago)
                ->execute($this->database);
            $this->log('This is dry-run, so no deletion is performed, however ' . Minion_CLI::color($deleted_rows->get('count'), 'yellow') . ' records would be deleted');
        } else {
            $deleted_rows = DB::delete($this->table_name)
                ->where('processed', 'IS NOT', null)
                ->where('processed', '<', $week_ago)
                ->execute($this->database);
            $this->log('Deleted ' . Minion_CLI::color($deleted_rows, 'yellow') . ' records');
        }
    }

    public function log($message, $endline = true, $vars = null, $type = Log::INFO) {
        if (php_sapi_name() == 'cli') {
            // Write message to STDOUT if executed in CLI
            Minion_CLI::write($message, $endline);
        }
        // Log message into file, but strip it from bash color codes first (if any)
        $this->log->add($type, preg_replace('/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/', '', $message), $vars);
    }

    public function getTableName() {
        return $this->table_name;
    }

    public function getDatabase() {
        return $this->database;
    }

    public static function getTypeName($type)
    {
        return Arr::get(static::$type_names, $type);
    }

} // End Notification
