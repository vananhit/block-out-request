<?php
  if (!defined('ABSPATH')) {
    exit;
  }

class AdminPage
{
	private static $instance;
	/**
	 * @var string The message to display after saving settings
	 */
	var $message = '';
	private static $admin_page_id='block-out-request';

	/**
	 * Jetpay_Admin_Page constructor.
	 */
	private function __construct()
	{
		add_action('admin_menu', array($this, 'register_menu_page'));
		add_action('admin_init', [$this,'my_plugin_init']);
		if(isset($_REQUEST["page"]) && $_REQUEST["page"] == self::$admin_page_id){
			if(isset($_REQUEST["active"])){
				$aciveKey = $_REQUEST["aciveKey"];
				$this->save_settings($aciveKey);
			}
			
		}
		
		// hiển thị thông báo lưu thành công
		add_action('admin_notices', [$this,'notify']);
	}
	function my_plugin_init() {
		if(isset($_REQUEST['scanner'])){
			$this->scanner();
		}
	}
	public function scanner(){
		try{
			$this->filter_urls_in_posts();
			$this->get_urls_from_theme();
			$this->message =
			'<div class="notice notice-success"><p><strong>' .
			'Quyét thành công' .
			'</p></strong></div>';
		}catch(Exception $e){
			$this->message =
				'<div class="error notice is-dismissible"><p><strong>' .
				'Có lỗi xẩy ra' .
				'</p></strong></div>' ;
		}
		
	}
	public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
	/**
	 * Save settings for the plugin
	 */
 	private function save_settings($aciveKey)
	{
		try{
			if(isset($aciveKey)){
				$isValid = false;
				if(isset($aciveKey)){
					$setting  = include RootDIR . 'setting/default-setting.php';
					$client =  new SheetClient($setting['spreadsheetId']['ActiveList']);
					$setting  = include RootDIR . 'setting/default-setting.php';
					$values =  $client->getRange('ActiveList!A:B');
					foreach($values as $value){
						if($value[0]==home_url() && $value[1]==$aciveKey){
							$isValid = true;
							break;
						}
					}
				}
				if($isValid){
					update_option(self::$admin_page_id,'ISACTIVE');
					$this->message  = "<script> document.addEventListener('DOMContentLoaded', function() {
						document.getElementById('scanner').click();
				}); </script>";
				}else{
					delete_option(self::$admin_page_id);
					$this->message =
					'<div class="error notice is-dismissible"><p><strong>' .
					'Kích hoạt thất bại' .
					'</p></strong></div>';
				}
			}
			
		}catch(Exception $e){
			$this->message =
				'<div class="error notice is-dismissible"><p><strong>' .
				'Có lỗi xẩy ra' .
				'</p></strong></div>' ;
		}
		
	}
	public function notify () {
		print_r($this->message);
	}


	/**
	 * Register the sub-menu under "WooCommerce"
	 * Link: http://my-site.com/wp-admin/admin.php?page=casso
	 */
	public function register_menu_page()
	{
		add_menu_page(
			'BlockLinkOut',      // Page title
			'BlockLinkOut',            // Menu title
			'manage_options',         // Capability
			self::$admin_page_id,      // Menu slug
			array($this,'admin_page_html'),// Function to display the page content
			'dashicons-admin-generic',// Icon URL
		);
	}
	/**
	 * Lấy danh sách các URL từ các file PHP trong theme.
	 */
	function get_urls_from_theme() {
		try{
			$client = new SheetClient();
			$res = $client->getRange('WhiteList!A:A');
			$hashTable = new UniqueArray();
			// Lặp qua các URL và kiểm tra xem chúng có hợp lệ không
			$updateData = array();
			if(isset($res)){
				foreach($res as $row){
					$hashTable->insert($row[0]);
				}
			}
			// Lấy thông tin về theme hiện tại
			$theme = wp_get_theme();
			$theme_path = $theme->get_stylesheet_directory();

			// Duyệt qua các file trong thư mục theme
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($theme_path, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($files as $file) {
				if ($file->isFile() &&  in_array($file->getExtension(),['php','html'])) {
					$file_contents = file_get_contents($file->getPathname());
					$urls = extractLinks($file_contents);
					foreach ($urls as $url) {
						//Kiểm tra hai url có phải là external url
						if (!are_urls_same_host(home_url(),$url)) {
							//Kiểm tra external url đã có trong white list chưa;
							if ($hashTable->includes($url)){
								//Nếu link tồn tại trong white list => bỏ qua
								continue;
							}
							$externalHost = parse_url($url, PHP_URL_HOST);
							$now = getCurrentDateTime();
							array_push($updateData,[home_url(),$file->getPathname(),$externalHost,$url,'n/a','n/a',$now]);
							if(!empty($updateData) && count($updateData) > 8000 ){
								//Ghi log các link ko nằm trong white list
								$client->append($updateData,'Scanner');
								$updateData = []; // Làm rỗng mảng
							}
						}
					}
				}
			}
			if(!empty($updateData)){
				//Ghi log các link ko nằm trong white list
				$client->append($updateData,'Scanner');
				$updateData = []; // Làm rỗng mảng
			}
		}catch(Exception $e){
			$message = "Caught exception: " . $e->getMessage() . "\n";
			$message .= "Stack trace:\n" . $e->getTraceAsString();
			error_log($message);
		}
	
	}
	// Hàm để hiển thị URL trong bài viết lúc cài đặt
	function filter_urls_in_posts() {
		try{

			$args = array(
				'posts_per_page' => 1000,
				'post_type' => array('post','navigator','page','template'),
				'paged' => 1 
			);
			$updateData = [];
			$client = new SheetClient();
			$res = $client->getRange('WhiteList!A:A');
			$hashTable = new UniqueArray();
			if(isset($res)){
				foreach($res as $row){
					$hashTable->insert($row[0]);
				}
			}

			while(true){
				$posts = get_posts($args);
				// Nếu không có bài đăng nào trả về, thoát vòng lặp
				if (empty($posts)) {
					break;
				}
				
				foreach ($posts as $post) {
					setup_postdata($post);
					$text = $post->post_content . ' ' . $post->post_title;
					$urls = extractLinks($text);
					$post_date_gmt = $post->post_modified_gmt; // Lấy thời gian UTC của bài viết
					$date = new DateTime( $post_date_gmt, new DateTimeZone( 'GMT' ) );
					$date->setTimezone( new DateTimeZone( 'Asia/Ho_Chi_Minh' ) );
					$post_date = $date->format( 'Y-m-d H:i:s' );
					$author_data = get_userdata($post->post_author);
					$author_display_name = $author_data->display_name;
					foreach ($urls as $url) {
						//Kiểm tra hai url có phải là external url
						if (!are_urls_same_host(home_url(),$url)) {
							//Kiểm tra external url đã có trong white list chưa;
							if ($hashTable->includes($url)){
							//Nếu link tồn tại trong white list => bỏ qua
								continue;
							}
							$externalHost = parse_url($url, PHP_URL_HOST);
							$now = getCurrentDateTime();
							array_push($updateData,[home_url(),get_permalink($post),$externalHost,$url,$author_display_name,$post_date ,$now]);
							if(!empty($updateData) && count($updateData)>8000){
								//Ghi log các link ko nằm trong white list
								$client->append($updateData,'Scanner');
								$updateData = [];
							}
						}
					}
				}
				// Tăng trang lên để lấy trang tiếp theo
				$args['paged']++;
			}

			wp_reset_postdata();

			if(!empty($updateData)){
				$client->append($updateData,'Scanner');
			}
		}catch(Exception $e){
			$message = "Caught exception: " . $e->getMessage() . "\n";
			$message .= "Stack trace:\n" . $e->getTraceAsString();
			error_log($message);
		}
	}
	/**
	 * Generate the HTML code of the settings page
	 */
	public function admin_page_html()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$setting  = include RootDIR . 'setting/default-setting.php';
		$ActiveList = $setting['spreadsheetId']['ActiveList'];
		$WhiteList = $setting['spreadsheetId']['WhiteList'];
		$isActive = get_option('block-out-request');
    	include('admin.html');
  	}	
}
?>


