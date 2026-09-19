<section class="dashboard-grid">
    <div class="panel">
        <div class="panel-head"><h3>Notification Channels</h3></div>
        <form method="post" action="<?= base_url('admin/notifications/save-settings') ?>" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>Email Enabled
                    <select name="email_enabled"><option value="1" <?= ($settings['email_enabled'] ?? '') === '1' ? 'selected' : '' ?>>Yes</option><option value="0" <?= ($settings['email_enabled'] ?? '') !== '1' ? 'selected' : '' ?>>No</option></select>
                </label>
                <label>From Email <input type="text" name="email_from" value="<?= e($settings['email_from'] ?? '') ?>"></label>
            </div>
            <label>Default Notification Email <input type="text" name="notify_default_email" value="<?= e($settings['notify_default_email'] ?? '') ?>"></label>
            <div class="form-grid">
                <label>WhatsApp Enabled
                    <select name="whatsapp_enabled"><option value="1" <?= ($settings['whatsapp_enabled'] ?? '') === '1' ? 'selected' : '' ?>>Yes</option><option value="0" <?= ($settings['whatsapp_enabled'] ?? '') !== '1' ? 'selected' : '' ?>>No</option></select>
                </label>
                <label>Default Phone <input type="text" name="notify_default_phone" value="<?= e($settings['notify_default_phone'] ?? '') ?>"></label>
            </div>
            <label>WhatsApp API URL <input type="text" name="whatsapp_api_url" value="<?= e($settings['whatsapp_api_url'] ?? '') ?>"></label>
            <label>WhatsApp API Token <input type="text" name="whatsapp_api_token" value="<?= e($settings['whatsapp_api_token'] ?? '') ?>"></label>
            <button class="btn btn-primary" type="submit">Save Channel Settings</button>
        </form>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>Add Notification Template</h3></div>
        <form method="post" action="<?= base_url('admin/notifications/templates/store') ?>" class="stack-form">
            <?= csrf_field() ?>
            <label>Event Key <input type="text" name="event_key" placeholder="new_lead"></label>
            <label>Label <input type="text" name="label" placeholder="New Lead"></label>
            <label>Subject <input type="text" name="subject"></label>
            <label>Email Body <textarea name="email_body" rows="4"></textarea></label>
            <label>WhatsApp Body <textarea name="whatsapp_body" rows="4"></textarea></label>
            <label>Enabled
                <select name="is_enabled"><option value="1">Yes</option><option value="0">No</option></select>
            </label>
            <button class="btn btn-primary" type="submit">Save Template</button>
        </form>

        <form method="post" action="<?= base_url('admin/notifications/send-test') ?>" class="stack-form top-space">
            <?= csrf_field() ?>
            <label>Test Event Key <input type="text" name="event_key" value="new_lead"></label>
            <label>Test Email <input type="text" name="email" value="<?= e($settings['notify_default_email'] ?? '') ?>"></label>
            <label>Test Phone <input type="text" name="phone" value="<?= e($settings['notify_default_phone'] ?? '') ?>"></label>
            <button class="btn btn-outline" type="submit">Send Test Notification</button>
        </form>
    </div>
</section>

<section class="dashboard-grid">
    <div class="panel">
        <div class="panel-head"><h3>Templates</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Event</th><th>Label</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?= e($template['event_key']) ?></td>
                            <td><?= e($template['label']) ?></td>
                            <td><?= e($template['subject']) ?></td>
                            <td><?= (int) $template['is_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></td>
                            <td>
                                <form method="post" action="<?= base_url('admin/notifications/templates/delete') ?>" onsubmit="return confirm('Delete this template?')">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                    <button class="btn btn-danger btn-small" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>Notification Logs</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Channel</th><th>Recipient</th><th>Status</th><th>When</th><th>Error</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['channel']) ?></td>
                            <td><?= e($log['recipient']) ?></td>
                            <td><?= e($log['status']) ?></td>
                            <td><?= e(format_date($log['created_at'], 'd M Y H:i')) ?></td>
                            <td><small><?= e($log['error_message']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
