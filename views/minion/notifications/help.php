Minion task to manage notifications process.

Usage:

    <?php echo $_SERVER['argv'][0]; ?> notifications {action} [--dry-run]

<?php if (isset($actions)): ?>
Where {action} is one of the following:

<?php foreach($actions as $action): ?>
  * <?php echo $action; ?>

<?php endforeach; ?>

Add '--dry-run' parameter to simulate given action (if applicable).

<?php endif; ?>

