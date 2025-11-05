<?php
// Vue partielle : notification
// $type : success, error, info, warning...
// $message : texte à afficher
if (!empty($message)) : ?>
    <div class="notice notice-<?= esc_attr($type) ?>"><p><?= esc_html($message) ?></p></div>
<?php endif; ?> 