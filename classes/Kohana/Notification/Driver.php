<?php defined('SYSPATH') OR die('No direct script access.');

interface Kohana_Notification_Driver
{
    public function send($dry_run = false);
}
