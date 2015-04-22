<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Sending notifications using email
 */
class Kohana_Notification_System_Mail extends Notification_System
{
    const TRANSPORT_SMTP = 'Smtp';
    const TRANSPORT_SENDMAIL = 'Sendmail';
    const TRANSPORT_MAIL = 'Mail';
    const TRANSPORT_LOADBALANCED = 'LoadBalanced';
    const TRANSPORT_FAILOVER = 'Failover';

    protected $destinations = array();

    protected $mail_config = array();

    protected $database;

    protected $table_name;

    public function __construct()
    {
        parent::__construct();
        if (!class_exists('Swift_Mailer')) {
            throw new ErrorException('Mail notifications require SwiftMailer library');
        }
        $this->mail_config = new Config_Group(Kohana::$config, 'mail_config', Arr::get($this->config->get('systems', array()), 'mail'));
        $this->database = Database::instance($this->config->get('database'));
        $this->table_name = $this->mail_config->get('table_name');
        if (!$this->table_name) {
            throw new Kohana_Exception('No table name for email notification system specified');
        }
    }

    public function send($dry_run = false)
    {
        // These folks will get all notifications, regardless of notification type
        $all_types_recipients = Arr::get($this->mail_config->destinations, '_all', array());

        $recipients = array();

        foreach (Arr::get($this->mail_config->destinations, $this->notification->type, array()) as $key => $value) {
            // (SwiftMailer way)
            if (Valid::email($key)) {
                if (!in_array($key, $recipients) and !array_key_exists($key, $recipients)) {
                    $recipients[$key] = $value;
                }
            } else {
                if (Valid::email($value)) {
                    // If the value is an email address - it's ready to be a recipient
                    if (!in_array($value, $recipients) and !array_key_exists($value, $recipients)) {
                        $recipients[] = $value;
                    }
                } else {
                    if (is_numeric($value)) {
                        // When just a number is specified - this is users's ID, so user needs to be loaded to get it's email
                        /** @var $user Model_User */
                        $user = ORM::factory('User', $value);
                    } elseif (strpos($value, '@') === 0) {
                        // '@' in front of string suggests whole group (in meaning of roles) of users (admins, centrala, dealers)
                        $role_name = substr($value, 1);
                        /** @var $role Model_Role */
                        $role = ORM::factory('Role', $role_name);
                        if ($role->loaded()) {
                            /** @var $user Database_Result */
                            $user = $role->users->find_all();
                        } else {
                            $this->notification_instance->log(
                                '[WARNING] Could not find user role ' . $role_name . ' - skipping ...',
                                true,
                                null,
                                Log::WARNING
                            );
                            continue;
                        }
                    } else {
                        // Try to find user by it's username
                        $user = ORM::factory('User', array('username' => $value));
                    }

                    if ($user instanceof Model_User and $user->loaded()) {
                        // Adding a single user instance
                        if (!in_array($user->email, $recipients) and !array_key_exists($user->email, $recipients)) {
                            $recipients[] = $user->email;
                        }
                    }
                    if ($user instanceof Database_Result and $user->count()) {
                        // Add group of users
                        foreach ($user->as_array(null, 'email') as $email) {
                            if (!in_array($email, $recipients) and !array_key_exists($email, $recipients)) {
                                $recipients[] = $email;
                            }
                        }
                    }
                }
            }
        }

        // Merge selected recipients with these, who get all notifications, regardless of their type
        $recipients = Arr::merge($all_types_recipients, $recipients);

        if (!count($recipients)) {
            $this->notification_instance->log('No recipients found for type ' . $this->notification->type . ', exiting...');
            return true; // return true to update notification record to not be processed again
        }

        $this->notification_instance->log(Minion_CLI::color('Collected recipients: ', 'yellow') . json_encode($recipients));

        $query = DB::insert(
            $this->table_name,
            array(
                'notification_id',
                'recipient',
            )
        )
        ->values(
            array(
                ':notification_id',
                ':recipient',
            )
        );

        $return = true;

        foreach ($recipients as $key => $value) {
            try {
                $v = Valid::email($key) ? json_encode(array($key => $value)) : $value;
                $this->notification_instance->log(
                    'Putting message ' . $this->notification->id . ' to ' . Minion_CLI::color($v, 'cyan') . ' into queue... ',
                    false
                );
                $query->param(':notification_id', $this->notification->id);
                $query->param(':recipient', $v);

                if ($dry_run) {
                    // Mimic successfull operation when running dry
                    $this->notification_instance->log(Minion_CLI::color('OK', 'green'));
                } else {
                    list($insert_id, $affected_rows) = $query->execute($this->database);

                    if ($affected_rows < 1) {
                        $this->notification_instance->log(Minion_CLI::color('FAILED', 'red'), true, null, Log::ERROR);
                        $return = false;
                    } else {
                        $this->notification_instance->log(Minion_CLI::color('OK', 'green'));
                    }
                }
            } catch (Database_Exception $e) {
                $this->notification_instance->log(Minion_CLI::color('FAILED' . $e, 'red'), true, null, Log::ERROR);
                $return = false;
            }
        }

        return $return;
    }

    public function process_mail_queue($dry_run = false)
    {
        $queued_recipients = DB::select('recipient')
            ->distinct(true)
            ->from($this->table_name)
            ->where('processed', '=', null);

        /** @var $result Database_Result */
        $result = $queued_recipients->execute($this->database);
        $recipients = $result->as_array(null, 'recipient');
        unset($result);

        if (count($recipients)) {
            $now = time();
            // Create the Transport
            $this->notification_instance->log('Creating SwiftMailer Transport... ', false);
            // Watch out for Mail transport: http://swiftmailer.org/docs/sending.html#the-mail-transport
            $transport = $this->getTransport();
            $this->notification_instance->log(Minion_CLI::color('OK', 'green'));

            $this->notification_instance->log('Creating Mailer instance using created Transport... ', false);
            // Create the Mailer using your created Transport
            $mailer = Swift_Mailer::newInstance($transport);
            $this->notification_instance->log(Minion_CLI::color('OK', 'green'));

            foreach($recipients as $recipient) {
                $recipient_messages = DB::select()
                    ->from($this->table_name)
                    ->where('processed', '=', null)
                    ->where('recipient', '=', $recipient);

                // Create a message
                $this->notification_instance->log('Creating message to ' . $recipient . '... ', false);

                if ($_recipient = json_decode($recipient, true) and is_array($_recipient) and count($_recipient)) {
                    $recipient = $_recipient;
                }

                $messages = $recipient_messages->execute($this->database)->as_array('id');
                $notification_ids = Arr::pluck($messages, 'notification_id');
                $notifications = DB::select()
                    ->from(Notification::instance()->getTableName())
                    ->where('id', 'IN', $notification_ids)
                    ->execute(Notification::instance()->getDatabase())
                    ->as_array('id');

                foreach ($messages as &$_message) {
                    $notification_id = Arr::get($_message, 'notification_id');
                    if ($notification_id) {
                        $_message['notification'] = Arr::get($notifications, $notification_id);
                    }
                }

                $message_body = View::factory('emails/notifications/default')->bind('messages', $messages);

                $message = Swift_Message::newInstance($this->mail_config->subject)
                    ->setFrom($this->mail_config->sender)
                    ->setTo($recipient)
                    ->setBody(
                        $message_body,
                        'text/html'
                    )
                    ->addPart(strip_tags($message_body), 'text/plain');
                $this->notification_instance->log(Minion_CLI::color('OK', 'green'));

                // Send the message
                $this->notification_instance->log('Sending message... ', false);
                if ($dry_run) {
                    $this->notification_instance->log(Minion_CLI::color('OK', 'green') . ' (dry-run: no messages are actually sent)'); // Mimic successfull sending
                } else {
                    try {
                        /** @var Swift_Mailer $mailer */
                        $result = $mailer->send($message);
                        $this->notification_instance->log(Minion_CLI::color('OK', 'green') . ' (sent ' . $result . ' messages)');
                    } catch (Swift_TransportException $e) {
                        Kohana::$log->add(Log::ERROR, $e);
                        $this->notification_instance->log(Minion_CLI::color('Failed (see log)', 'red'), true, null, Log::ERROR);
                    }

                    DB::update($this->table_name)
                        ->set(array('processed' => $now))
                        ->where('id', 'IN', array_keys($messages))
                        ->execute($this->database);
                }
            }

        } else {
            $this->notification_instance->log(Minion_CLI::color('No pending mails found - exiting.', 'yellow'));
        }

    }

    public function clean_mail_queue($dry_run = false)
    {
        $this->notification_instance->log('Preparing to clean ' . Minion_CLI::color($this->table_name, 'yellow') . ' table');
        $now = time();
        $week_ago = $now - 7 * 24 * 60 * 60;
        $this->notification_instance->log('All records processed before ' . Minion_CLI::color(date('Y-m-d H:i:s', $week_ago), 'yellow') . ' (a week ago) will be DELETED');
        if ($dry_run) {
            $deleted_rows = DB::select(array(DB::expr('COUNT(id)'), 'count'))
                ->from($this->table_name)
                ->where('processed', 'IS NOT', null)
                ->where('processed', '<', $week_ago)
                ->execute($this->database);
            $this->notification_instance->log('This is dry-run, so no deletion is performed, however ' . Minion_CLI::color($deleted_rows->get('count'), 'yellow') . ' records would be deleted');
        } else {
            $deleted_rows = DB::delete($this->table_name)
                ->where('processed', 'IS NOT', null)
                ->where('processed', '<', $week_ago)
                ->execute($this->database);
            $this->notification_instance->log('Deleted ' . Minion_CLI::color($deleted_rows, 'yellow') . ' records');
        }
    }

    protected function getTransport()
    {
        $type = $this->mail_config->get('transport', static::TRANSPORT_MAIL);
        $config = $this->mail_config->get('transport_config');

        switch ($type) {
            case static::TRANSPORT_SMTP:
                $config = (array)$config;
                $host = Arr::get($config, 'host');
                $port = Arr::get($config, 'port', 25);
                $username = Arr::get($config, 'username');
                $password = Arr::get($config, 'password');
                return Swift_SmtpTransport::newInstance($host, $port)
                    ->setUsername($username)
                    ->setPassword($password);
                break;
            case static::TRANSPORT_SENDMAIL:
                // Something like: $config = '/usr/sbin/sendmail -bs'
                return Swift_SendmailTransport::newInstance($config);
            default:
                // For everything else (Mail, LoadBalanced and Failover):
                return Swift_MailTransport::newInstance();
        }
    }
}
