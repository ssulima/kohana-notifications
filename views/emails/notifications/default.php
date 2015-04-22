<?php defined('SYSPATH') or die('No direct script access.'); ?>
<h1>
    Notification from your application
</h1>
<?php if (isset($messages)): ?>
<table border="1" cellpadding="1" cellspacing="0" style="border: 1px black solid; width: 100%; background: #ffffff;">
    <thead>
    <tr style="background: #f0f0f0; color: #ff0018;">
        <th>Date</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    </thead>
    <?php foreach ($messages as $message):
        $notification = Arr::get($message, 'notification', array());
        $created = Arr::get($notification, 'created');
        ?>
        <tr>
            <td><?php echo $created ? date('Y-m-d H:i:s', $created) : '-'; ?></td>
            <td><?php echo Notification::getTypeName(Arr::get($notification, 'type')); ?></td>
            <td><?php echo strip_tags(Arr::get($notification, 'description')); ?></td>
        </tr>
    <?php endforeach; ?>
    <tfoot>
    <tr style="background: #f0f0f0; color: #ff0018;">
        <th>Date</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    </tfoot>
</table>
<?php else: ?>
Brak powiadomień
<?php endif; ?>
