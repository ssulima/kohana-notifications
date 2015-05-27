<?php defined('SYSPATH') OR die('No direct access allowed.');

return array
(
    'database' => 'default',
    'table_name' => 'notifications',
    'type_systems' => array(
        // Common system notifications
        Notification::TYPE_ERROR => array(
            'mail',
//            'rss', // Not supported yet
        ),
        Notification::TYPE_DEBUG            => array('mail'),
        Notification::TYPE_EMERGENCY        => array('mail'),
        Notification::TYPE_WARNING          => array('mail'),
        Notification::TYPE_INFO             => array('mail'),
        // Auth notifications
        Notification::TYPE_LOGIN_FAILED     => array('mail'),
        Notification::TYPE_ACCESS_DENIED    => array('mail'),
        // ...
    ),
    // To not process all notification messages at once, split them into chunks
    'batch_limit' => 100,
    'systems' => array(
        'mail' => array(
            // Email subject
            'subject' => 'Notification subject',
            // possible values: 'Smtp', 'Sendmail', 'Mail', 'LoadBalanced', 'Failover'
            // Transport configuration templates:
            // SMTP:
            //     'transport_config' => array(
            //         'host' => 'example.com',
            //         'port' => '',
            //         'username' => null,
            //         'password' => null,
            //     )
            //
            // SENDMAIL:
            //     'transport_config' => '/usr/sbin/sendmail -bs'
            //
            // MAIL/NATIVE:
            //     'transport_config' => '-f%s'
            //
            // @see 'TRANSPORT_*' consts in modules/notification/classes/Kohana/Notification/System/Mail.php
            // @see http://swiftmailer.org/docs/overview.html#transports
            'transport' => Notification_System_Mail::TRANSPORT_MAIL,
            'transport_config' => null,
            'table_name' => 'notifications_mail_queue',
            // Email sender, can be provided in SwiftMailer style, i.e. array('email@address.com' => 'Pretty Sender Name')
            'sender' => null,
            'destinations' => array(
                // Possible definitions:
                //  - regular e-mail address:              'operator@example.com'
                //  - SwiftMailer way:                     'john.doe@example.com' => 'John Doe'
                //  - users's ID as in 'users' table:      3
                //  - user's username as in 'users' table: 'admin'
                //  - all users from a group/role:         '@admin'

                '_all' => array(), // Recipient of each and every notification, regardless of it's type
                // Common system notifications
                Notification::TYPE_ERROR => array(),
                Notification::TYPE_DEBUG => array(),
                Notification::TYPE_EMERGENCY => array(),
                Notification::TYPE_WARNING => array(),
                Notification::TYPE_INFO => array(),
                // Auth notifications
                Notification::TYPE_ACCESS_DENIED => array(),
                Notification::TYPE_LOGIN_FAILED => array(),
            ),
        ),
//        'rss' => array(), // not yet supported
    ),
);
