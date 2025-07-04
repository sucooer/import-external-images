<?php
/*
Plugin Name: Import External Images
Description: 批量导入文章中外链图片并保存本地，支持搜索、筛选、分页导入
Version: 1.0
Author: anyaer
*/
// ====== TinyPNG API 配置 ======
$api_key = get_option('tinypng_api_key');

function compress_image_with_tinypng($file_path) {
    $api_key = get_option('tinypng_api_key'); // 动态获取API Key
    if (!$api_key) return false;
    if (!file_exists($file_path)) return false;
    $request = curl_init();
    curl_setopt_array($request, array(
        CURLOPT_URL => "https://api.tinify.com/shrink",
        CURLOPT_USERPWD => "api:" . $api_key,
        CURLOPT_POSTFIELDS => file_get_contents($file_path),
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
    ));
    $response = curl_exec($request);
    $header_size = curl_getinfo($request, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    curl_close($request);
    if (preg_match('/Location: (https:\/\/api\.tinify\.com\/output\/[^\s]+)/', $headers, $matches)) {
        $compressed_url = trim($matches[1]);
        $compressed_image = file_get_contents($compressed_url, false, stream_context_create([
            "http" => [
                "header" => "Authorization: Basic " . base64_encode("api:$api_key")
            ]
        ]));
        if ($compressed_image) {
            file_put_contents($file_path, $compressed_image);
            return true;
        }
    }
    return false;
}

// ✅ 设置上传路径为固定目录 post-images
function custom_upload_dir_for_post_images($dirs) {
    if (defined('IMPORTING_EXTERNAL_IMAGE') && IMPORTING_EXTERNAL_IMAGE) {
        $upload_path = 'post-images';  // 不使用年月分目录
        $dirs['path'] = $dirs['basedir'] . '/' . $upload_path;
        $dirs['url'] = $dirs['baseurl'] . '/' . $upload_path;
        $dirs['subdir'] = '';
    }
    return $dirs;
}
add_filter('upload_dir', 'custom_upload_dir_for_post_images');

// ✅ 添加后台菜单入口
add_action('admin_menu', function () {
    add_management_page('导入外链图片', '导入外链图片', 'manage_options', 'import-external-images', 'render_import_external_images_page');
    add_options_page('TinyPNG设置', 'TinyPNG设置', 'manage_options', 'tinypng-settings', 'tinypng_settings_page');
});

// ✅ 渲染后台页面 + 表单
function render_import_external_images_page() {
    $last_images_count = 0;
    if (isset($_POST['run_import']) && current_user_can('manage_options')) {
        if (!empty($_POST['post_ids']) && is_array($_POST['post_ids'])) {
            $ids = array_map('intval', $_POST['post_ids']);
            $images_count = 0;
            foreach ($ids as $id) {
                $images_count += import_external_images_for_post($id);
            }
            $last_images_count = $images_count;
            echo '<div class="updated"><p>✅ 共处理 ' . count($ids) . ' 篇文章，处理了 ' . $images_count . ' 张图片。</p></div>';
        } else {
            echo '<div class="error"><p>⚠️ 请至少选择一篇文章。</p></div>';
        }
    }

    // 获取分页参数和搜索
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        's'              => $search,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
    );
    $query = new WP_Query($args);
    $posts = $query->posts;

    echo '<div class="wrap"><h2>批量导入文章中的外链图片</h2>
    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="import-external-images">
        <input type="text" name="s" value="' . esc_attr($search) . '" placeholder="搜索文章标题">
        <input type="submit" class="button" value="搜索">
    </form>';

    echo '<form method="post">
        <p>勾选需要处理的文章：</p>
        <label><input type="checkbox" id="select-all"> 全选</label><br><br>';

    foreach ($posts as $post) {
        // 统计外链图片数量
        $content = $post->post_content;
        $external_img_count = 0;
        if (preg_match_all('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/', $content, $matches)) {
            $urls = array_unique($matches[1]);
            foreach ($urls as $url) {
                if (strpos($url, home_url()) === false) {
                    $external_img_count++;
                }
            }
        }
        echo '<label style="display:block; margin-bottom:5px;">
                <input type="checkbox" name="post_ids[]" value="' . $post->ID . '">
                ' . esc_html($post->post_title) . ' (ID: ' . $post->ID . ', 外链图片: ' . $external_img_count . ')
              </label>';
    }

    echo '<br><input type="submit" class="button button-primary" name="run_import" value="开始导入所选文章的图片">
        </form>';

    // 分页导航
    $total_pages = $query->max_num_pages;
    if ($total_pages > 1) {
        echo '<div class="tablenav-pages" style="margin-top:20px;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $url = add_query_arg(array_merge($_GET, ['paged' => $i]));
            $class = $i == $paged ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . esc_url($url) . '">' . $i . '</a> ';
        }
        echo '</div>';
    }

    echo '</div>';

    // ✅ 添加全选脚本
    echo '<script>
    document.getElementById("select-all").addEventListener("change", function(e) {
        const checkboxes = document.querySelectorAll("input[name=\'post_ids[]\']");
        checkboxes.forEach(cb => cb.checked = e.target.checked);
    });
    </script>';
}

// ✅ 核心逻辑：处理单篇文章中的外链图像
function import_external_images_for_post($post_id) {
    $post = get_post($post_id);
    if (!$post) return 0;

    $content = $post->post_content;
    $images_processed = 0;

    if (preg_match_all('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/', $content, $matches)) {
        $urls = array_unique($matches[1]);
        $updated = false;
        $img_index = 1; // 新增序号

        foreach ($urls as $url) {
            if (strpos($url, home_url()) !== false) continue;

            if (!defined('IMPORTING_EXTERNAL_IMAGE')) {
                define('IMPORTING_EXTERNAL_IMAGE', true);
            }
            $tmp = download_url($url);
            if (is_wp_error($tmp)) continue;

            // ====== 新增：大于100KB自动用TinyPNG压缩 ======
            if (file_exists($tmp) && filesize($tmp) > 102400) {
                compress_image_with_tinypng($tmp);
            }
            // ====== End TinyPNG ======

            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $file_array = array(
                'name'     => $post_id . '-' . $img_index . '.' . $ext,
                'tmp_name' => $tmp,
            );
            $img_index++;

            $id = media_handle_sideload($file_array, $post_id);
            if (!is_wp_error($id)) {
                $new_url = wp_get_attachment_url($id);
                $content = str_replace($url, $new_url, $content);
                $updated = true;
                $images_processed++;
            }
        }

        if ($updated) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content,
            ));
        }
    }
    return $images_processed;
}

// 设置页面内容
function tinypng_settings_page() {
    ?>
    <div class="wrap">
        <h2>TinyPNG API 设置</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('tinypng_options_group');
            do_settings_sections('tinypng-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">TinyPNG API Key</th>
                    <td>
                        <input type="text" name="tinypng_api_key" value="<?php echo esc_attr(get_option('tinypng_api_key')); ?>" size="40" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 注册设置
add_action('admin_init', function () {
    register_setting('tinypng_options_group', 'tinypng_api_key');
});
