<?php
/*
Plugin Name: Block Outbound HTTP Requests
Description: Chặn tất cả các yêu cầu HTTP ra ngoài từ WordPress.
Version: 1.0
Author: dvanh
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
define('RootDIR', plugin_dir_path(__FILE__));
define('RootURL', plugins_url('/', __FILE__));
include 'utils/client.php';
include 'utils/common-function.php';
include 'admin/admin-page.php';

$admin = AdminPage::getInstance();

function filter_invalid_urls_in_text($text,$isInsert=false,$isReplace=false){
    //$urls  = extract_urls_from_text($text);
    $urls = extractLinks($text);
     // Lặp qua các URL và kiểm tra xem chúng có hợp lệ không
     $updateData = array();
     $client = new SheetClient();
     $res = $client->getRange('WhiteList!A:A');
     $hashTable = new UniqueArray();
     if(isset($res)){
        foreach($res as $row){
            $hashTable->insert($row[0]);
         }
     }
 
     foreach ($urls as $url) {
        //Kiểm tra hai url có phải là external url
        if (!are_urls_same_host(home_url(),$url)) {
            //Kiểm tra external url đã có trong white list chưa;
            if ($hashTable->includes($url)){
                //Nếu link tồn tại trong white list => bỏ qua
                continue;
            }
            $current_user = wp_get_current_user(); // người dùng hiện tại
            $user_email = $current_user->user_email ; // email
            $display_name = $current_user->display_name;//tên hiển thị
            $user_login = $current_user->user_login;// tên người dùng
            $externalHost = parse_url($url, PHP_URL_HOST);
            $now = getCurrentDateTime();
            array_push($updateData,[home_url(),$user_login,$display_name,$user_email,$externalHost,$url,$now]);
            // Nếu URL không hợp lệ, thay thế nó bằng một chuỗi trắng trong văn bản
            if($isReplace){
                $text = str_replace($url, '', $text);
            }
        }
    }
    if($isInsert && !empty($updateData)){
        //Ghi log các link ko nằm trong white list
        $client =  new SheetClient();
        $client->append($updateData,'Notify');
    }
    // Trả về văn bản đã được lọc
    return $text;
}

/**
 * Custom function that runs when a post is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function save_post_hook($post_id, $post, $update) {
    try{
        if(in_array($post->post_type, ['page','post'])){
            filter_invalid_urls_in_text($post->post_title,true,false);
            filter_invalid_urls_in_text($post->post_content,true,false);
        }
    }catch(Exception $ex){
        // $message = "Caught exception: " . $e->getMessage() . "\n";
        // $message .= "Stack trace:\n" . $e->getTraceAsString();
        // error_log($message);
    }
}

// Hàm để loại bỏ các liên kết từ nội dung bài viết
function remove_links_from_post($content) {
    if (is_admin()) {
        return $content;
    }
    
    //Tìm tất cả các thẻ <a> và thay thế chúng bằng văn bản không có liên kết
    try{
        $ans =  filter_invalid_urls_in_text($content,false,true);
        return $ans;
    }catch(Exception $e){
        // $message = "Caught exception: " . $e->getMessage() . "\n";
        // $message .= "Stack trace:\n" . $e->getTraceAsString();
        // error_log($message);
    }
    return $content;
}

function catch_site_title_change( $option, $old_value, $new_value ) {
    // Check if the updated option is 'blogname' (site title)
    try{
        if ( $option === 'blogname' ) {
            filter_invalid_urls_in_text($new_value,true,false);
        }
    }catch(Exception $ex){
        // $message = "Caught exception: " . $e->getMessage() . "\n";
        // $message .= "Stack trace:\n" . $e->getTraceAsString();
        //error_log($message);
    }
   
}

function modify_blogname_value( $value ) {
    try{
        // Check if the current request is for the admin area, REST API, or Site Editor
        if ( current_user_can( 'administrator' )) {
            return $value;
        }
        return filter_invalid_urls_in_text($value,false,true);
    }catch(Exception $ex){
        // $message = "Caught exception: " . $e->getMessage() . "\n";
        // $message .= "Stack trace:\n" . $e->getTraceAsString();
        // error_log($message);
        return  $value;
    }
}

add_action('rest_post_dispatch', function($response, $server, $request) {
    try {
        // Get the current route of the REST API request
        $route = $request->get_route();

        // Check if the request is for the Site Editor, Customizer, or specific theme-related endpoints
        if (strpos($route, '/wp/v2/template-parts') !== false || strpos($route, '/wp/v2/navigation') !== false || strpos($route, '/wp/v2/templates') !== false) {
            $request_body = $request->get_body();
            $payload = json_decode($request_body);
            if(isset($payload)){
                $content = $payload->content;
                filter_invalid_urls_in_text($content,true,false);
            }
        }
        // Return the modified response
        return $response;
    } catch (Exception $ex) {
        // Log the exception details
        // $message = "Caught exception: " . $ex->getMessage() . "\n";
        // $message .= "Stack trace:\n" . $ex->getTraceAsString();
        // error_log($message);
        return $response;
    }
}, 10, 3);

// Chèn đoạn JavaScript vào trang
function add_custom_script() {
    // Kiểm tra xem có phải trang admin không để loại bỏ hiển thị đoạn script
    if (!is_admin()) {
        $flatWL= [];
        try{

            $client = new SheetClient();
            $res = $client->getRange('WhiteList!A:A');
            if(isset($res)){
                foreach($res as $row){
                    array_push($flatWL, $row[0]);
                }
            }
        }catch(Exception $ex){
             // Log the exception details
            // $message = "Caught exception: " . $ex->getMessage() . "\n";
            // $message .= "Stack trace:\n" . $ex->getTraceAsString();
            // error_log($message);
        }
        ?>
        <script>
            // Đoạn JavaScript để lọc liên kết không nằm trong whitelist
            (function() {
                var jsonWL = <?php echo json_encode($flatWL); ?>;
                var whitelist = new Set(jsonWL);
                // Hàm kiểm tra liên kết có trong whitelist và không cùng host với home_url
                function isInWhitelist(url) {
                    var urlObject = new URL(url);
                    var urlHost = urlObject.hostname;
                    var homeURL = "<?php echo home_url(); ?>";
                    var homeURLObject = new URL(homeURL);
                    var homeHost = homeURLObject.hostname;
                    return whitelist.has(url) || urlHost === homeHost;
                }

                // Lọc qua tất cả thẻ <a> mà không có href hợp lệ
                var anchorTags = document.getElementsByTagName('a');
                for (var j = anchorTags.length - 1; j >= 0; j--) {
                    var href = anchorTags[j].getAttribute('href');
                    if (!href || !isInWhitelist(href)) {
                        anchorTags[j].parentNode.removeChild(anchorTags[j]);
                    }
                }

                // Lấy tất cả các phần tử văn bản chứa liên kết
                var textNodes = document.evaluate("//text()", document.body, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
                for (var i = 0; i < textNodes.snapshotLength; i++) {
                    var node = textNodes.snapshotItem(i);
                    var content = node.textContent;

                    // Thay đổi nội dung văn bản dựa trên regex mới
                    var replacedContent = content.replace(/(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})/ig, function(match) {
                        return isInWhitelist(match) ? match : '';
                    });

                    // Nếu nội dung thay đổi thành rỗng, xóa node văn bản đó
                    if (replacedContent.trim() === '') {
                        node.parentNode.removeChild(node);
                    } else {
                        node.textContent = replacedContent;
                    }
                }
            })();
        </script>
        <?php
    }
}

// Cập nhật file .htaccess để chặn quyền truy cập file credentials từ internet
register_activation_hook(__FILE__, function () {
    try{
        $setting  = include RootDIR . 'setting/default-setting.php';
        $client =  new SheetClient($setting['spreadsheetId']['ActiveList']);
        $client->append([[home_url(),bin2hex(openssl_random_pseudo_bytes(16))]],'ActiveList');
    }catch(Exception $ex){
        error_log($ex->getMessage());
    }
    $htaccess_file = ABSPATH . '.htaccess'; // Path to .htaccess file
    
    // Check if .htaccess file exists and is writable
    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $rules = "# BEGIN Prevent Direct Access to block-out-request-credentials.json\n";
        
        // Add rule to prevent access to specific file
        $rules .= "<Files \"block-out-request-credentials.json\">\n";
        $rules .= "Order allow,deny\n";
        $rules .= "Deny from all\n";
        $rules .= "</Files>\n";
        
        $rules .= "# END Prevent Direct Access to block-out-request-credentials.json\n";
        
        // Update .htaccess file
        $htaccess_content = file_get_contents($htaccess_file);
        if (strpos($htaccess_content, '# BEGIN Prevent Direct Access to block-out-request-credentials.json') === false) {
            // If rule doesn't exist, add it
            file_put_contents($htaccess_file, $rules, FILE_APPEND);
        } else {
            // If rule already exists, update it
            $htaccess_content = preg_replace('/# BEGIN Prevent Direct Access to block-out-request-credentials.json.*# END Prevent Direct Access to block-out-request-credentials.json/s', $rules, $htaccess_content);
            file_put_contents($htaccess_file, $htaccess_content);
        }
    } else {
        // .htaccess file is not writable
        error_log('Unable to update .htaccess file. Please make sure it exists and is writable.');
    }
});

// Hook into plugin deactivation to remove added rules from .htaccess file
register_deactivation_hook(__FILE__, function() {
    $htaccess_file = ABSPATH . '.htaccess'; // Path to .htaccess file
    
    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        $htaccess_content = preg_replace('/# BEGIN Prevent Direct Access to block-out-request-credentials.json.*# END Prevent Direct Access to block-out-request-credentials.json/s', '', $htaccess_content);
        file_put_contents($htaccess_file, $htaccess_content);
    }
    delete_option('block-out-request');
});

$isActive = get_option('block-out-request');
if(isset($isActive) && $isActive == 'ISACTIVE'){
    add_action('save_post', 'save_post_hook', 10, 3);
    // Áp dụng hàm loại bỏ liên kết cho nội dung bài viết trước khi nó được hiển thị
    add_filter('the_content', 'remove_links_from_post');
    // Áp dụng hàm loại bỏ liên kết cho tiêu đề bài viết
    add_filter('the_title', 'remove_links_from_post');
    add_action( 'update_option', 'catch_site_title_change', 10, 3 );
    add_action('wp_footer', 'add_custom_script');
    add_filter( 'option_blogname', 'modify_blogname_value' );
}

?>
