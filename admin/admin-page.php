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
		if(isset($_REQUEST["page"]) && $_REQUEST["page"] == self::$admin_page_id){
			if(isset($_REQUEST["active"])){
				$aciveKey = $_REQUEST["aciveKey"];
				$this->save_settings($aciveKey);
			}
		}

		// hiển thị thông báo lưu thành công
		add_action('admin_notices', [$this,'notify']);
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
			array($this,'admin_page_html'),      // Function to display the page content
			'dashicons-admin-generic',// Icon URL
		);
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