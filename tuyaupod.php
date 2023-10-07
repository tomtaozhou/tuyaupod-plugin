<?php
/*
Plugin Name: Tuya to UPOD Post
Description: Fetch data from Tuya and post it to UPOD.
Version: 1.0
Author: Tao Zhou
*/

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
    if ($_POST['tuya_endpoint'] && $_POST['tuya_token']) {
        update_option('tuya_endpoint', $_POST['tuya_endpoint']);
        update_option('tuya_token', $_POST['tuya_token']);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $tuya_endpoint = get_option('tuya_endpoint', '');
    $tuya_token = get_option('tuya_token', '');
    ?>
    <div class="wrap">
        <h2>Tuya CO2 to Post Settings</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Tuya API Endpoint</th>
                    <td><input type="text" name="tuya_endpoint" value="<?php echo esc_attr($tuya_endpoint); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Tuya Access Token</th>
                    <td><input type="text" name="tuya_token" value="<?php echo esc_attr($tuya_token); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 从Tuya获取数据并发布到WordPress的功能
function fetch_tuya_and_post() {
    $tuya_endpoint = get_option('tuya_endpoint', '');
    $tuya_token = get_option('tuya_token', '');

    $headers = array(
        'Authorization' => 'Bearer ' . $tuya_token
        // 其他需要的头部信息
    );

    $response = wp_remote_get($tuya_endpoint, array('headers' => $headers));
    if (is_wp_error($response)) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $co2_concentration = $data['result'][0]['value'];  // 假设这是二氧化碳浓度的字段

    $post_data = array(
        'post_title'   => 'CO2 Concentration Report',
        'post_content' => "Current CO2 concentration is {$co2_concentration} ppm",
        'post_status'  => 'publish',
    );

    wp_insert_post($post_data);
}

// 定时任务
if (!wp_next_scheduled('fetch_tuya_and_post')) {
    wp_schedule_event(time(), 'hourly', 'fetch_tuya_and_post');
}

add_action('fetch_tuya_and_post', 'fetch_tuya_and_post');
?>
