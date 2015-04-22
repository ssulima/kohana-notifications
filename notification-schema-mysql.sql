CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `type` varchar(20) NOT NULL,
    `description` varchar(200) NOT NULL,
    `created` int(10) NOT NULL,
    `processed` int(10) unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `notifications_mail_queue` (
    `id` int(11) unsigned NOT NULL,
    `notification_id` int(11) unsigned NOT NULL,
    `recipient` varchar(200) NOT NULL,
    `processed` int(10) unsigned DEFAULT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
