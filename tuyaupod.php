<?php
/*
Plugin Name: Tuya CO2 to Post
Description: Fetch CO2 data from Tuya devices and post it to a specified WordPress post.
Version: 1.0
Author: Your Name
*/

// 创建自定义时间间隔
function tuya_add_cron_intervals($schedules) {
    $schedules['5_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display'  => 'Every 5 Minutes',
    );

    $schedules['30_minutes'] = array(
        'interval' => 1800, // 30 minutes in seconds
        'display'  => 'Every 30 Minutes',
    );

    return $schedules;
}
add_filter('cron_schedules', 'tuya_add_cron_intervals');

// 添加设置菜单
function tuya_to_post_menu() {
    add_options_page(
        'Tuya CO2 to Post Settings',
        'Tuya CO2 to Post',
        'manage_options',
        'tuya-co2-to-post',
        'tuya_to_post_settings_page'
    );
}
add_action('admin_menu', 'tuya_to_post_menu');

// 设置页面内容
function tuya_to_post_settings_page() {
    // Handle adding a new device
    if ($_POST['tuya_new_device_endpoint'] && $_POST['tuya_new_device_token']) {
        $devices = get_option('tuya_devices', array());
        $devices[] = array(
            'endpoint' => $_POST['tuya_new_device_endpoint'],
            'token' => $_POST['tuya_new_device_token']
        );
        update_option('tuya_devices', $devices);
    }

    // Handle deleting a device
    if (isset($_POST['delete_device_index'])) {
        $devices = get_option('tuya_devices', array());
        unset($devices[intval($_POST['delete_device_index'])]);
        $devices = array_values($devices);  // Reindex the array
        update_option('tuya_devices', $devices);
    }

    // Handle setting the target post
    if ($_POST['target_post_id']) {
        update_option('tuya_target_post_id', intval($_POST['target_post_id']));
    }

    // Handle setting the fetch frequency
    if ($_POST['fetch_frequency']) {
        update_option('tuya_fetch_frequency', $_POST['fetch_frequency']);
        // Reschedule the event with the new frequency
        wp_clear_scheduled_hook('fetch_tuya_and_update_post');
        wp_schedule_event(time(), $_POST['fetch_frequency'], 'fetch_tuya_and_update_post');
    }

    // Handle editing a device
    if (isset($_POST['edit_device_index'])) {
        $devices = get_option('tuya_devices', array());
        $edit_device = $devices[intval($_POST['edit_device_index'])];
    }

    // Handle saving edits to a device
    if (isset($_POST['save_device_index'])) {
        $devices = get_option('tuya_devices', array());
        $devices[intval($_POST['save_device_index'])] = array(
            'endpoint' => $_POST['tuya_edit_device_endpoint'],
            'token' => $_POST['tuya_edit_device_token']
        );
        update_option('tuya_devices', $devices);
    }

    $devices = get_option('tuya_devices', array());
    $target_post_id = get_option('tuya_target_post_id', 0);
    $fetch_frequency = get_option('tuya_fetch_frequency', 'hourly');
    ?>
    <div class="wrap">
        <h2>Tuya CO2 to Post Settings</h2>

        <h3>Target Post</h3>
        <form method="post" action="">
            <label for="target_post_id">Post to update with CO2 data:</label>
            <select name="target_post_id">
                <?php
                $args = array(
                    'post_type' => 'post',
                    'posts_per_page' => -1
                );
                $query = new WP_Query($args);
                while ($query->have_posts()) : $query->the_post();
                ?>
                    <option value="<?php the_ID(); ?>" <?php selected($target_post_id, get_the_ID()); ?>><?php the_title(); ?></option>
                <?php endwhile; wp_reset_postdata(); ?>
            </select>
            <?php submit_button('Set Target Post'); ?>
        </form>

        <h3>Fetch Frequency</h3>
        <form method="post" action="">
            <label for="fetch_frequency">How often should the data be fetched:</label>
            <select name="fetch_frequency">
                <option value="5_minutes" <?php selected($fetch_frequency, '5_minutes'); ?>>Every 5 Minutes</option>
                <option value="30_minutes" <?php selected($fetch_frequency, '30_minutes'); ?>>Every 30 Minutes</option>
                <option value="hourly" <?php selected($fetch_frequency, 'hourly'); ?>>Hourly</option>
            </select>
            <?php submit_button('Set Frequency'); ?>
        </form>

        <?php if (isset($edit_device)) : ?>
            <h3>Edit Device</h3>
            <form method="post" action="">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Tuya Device API Endpoint</th>
                        <td><input type="text" name="tuya_edit_device_endpoint" value="<?php echo esc_attr($edit_device['endpoint']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Tuya Device Access Token</th>
                        <td><input type="text" name="tuya_edit_device_token" value="<?php echo esc_attr($edit_device['token']); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <input type="hidden" name="save_device_index" value="<?php echo esc_attr($_POST['edit_device_index']); ?>" />
                <?php submit_button('Save Changes'); ?>
            </form>
        <?php endif; ?>

        <h3>Devices</h3>
        <table class="form-table">
            <?php foreach ($devices as $index => $device) : ?>
                <tr valign="top">
                    <td><?php echo esc_html($device['endpoint']); ?></td>
                    <td>
                        <form method="post" action="" style="display:inline-block;">
                            <input type="hidden" name="edit_device_index" value="<?php echo $index; ?>" />
                            <?php submit_button('Edit', 'primary small', 'submit', false); ?>
                        </form>
                        <form method="post" action="" style="display:inline-block;">
                            <input type="hidden" name="delete_device_index" value="<?php echo $index; ?>" />
                            <?php submit_button('Delete', 'delete small', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3>Add New Device</h3>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Tuya Device API Endpoint</th>
                    <td><input type="text" name="tuya_new_device_endpoint" value="" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Tuya Device Access Token</th>
                    <td><input type="text" name="tuya_new_device_token" value="" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Add Device'); ?>
        </form>
    </div>
    <?php
}

// 从Tuya获取数据并更新指定的WordPress文章
function fetch_tuya_and_update_post() {
    $devices = get_option('tuya_devices', array());
    $target_post_id = get_option('tuya_target_post_id', 0);
    if (!$target_post_id) {
        return;  // No target post set
    }

    foreach ($devices as $device) {
        $headers = array('Authorization' => 'Bearer ' . $device['token']);
        $response = wp_remote_get($device['endpoint'], array('headers' => $headers));
        if (is_wp_error($response)) {
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $co2_concentration = $data['result'][0]['value'];  // Assuming this is the CO2 concentration field

        // Update the specified post with the new data
        wp_update_post(array(
            'ID' => $target_post_id,
            'post_content' => "Current CO2 concentration is {$co2_concentration} ppm"
        ));
    }
}

// 定时任务
$fetch_frequency = get_option('tuya_fetch_frequency', 'hourly');
if (!wp_next_scheduled('fetch_tuya_and_update_post')) {
    wp_schedule_event(time(), $fetch_frequency, 'fetch_tuya_and_update_post');
}

add_action('fetch_tuya_and_update_post', 'fetch_tuya_and_update_post');
?>
