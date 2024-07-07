<?php 
  if (!defined('ABSPATH')) {
    exit;
  }
  include RootDIR . 'google-api-php-client/vendor/autoload.php';
  use Google\Client;
  use Google\Service\Sheets;
  // Đường dẫn tới tệp credentials JSON của bạn
  define('CREDENTIALS_PATH', __DIR__ . DIRECTORY_SEPARATOR .'block-out-request-credentials.json');
  class SheetClient{
    private $service;
    private $spreadsheetId;
    public function __construct($spreadsheetId =null) {
      // Tạo client và thiết lập thông tin xác thực
      $client = new Client();
      $client->setApplicationName('Google Sheets API PHP');
      $client->setScopes([Sheets::SPREADSHEETS]);
      $client->setAuthConfig(CREDENTIALS_PATH);
      $client->setAccessType('offline');
      // Khởi tạo dịch vụ Google Sheets API
      $this -> service = new Sheets($client);
      $setting  = include RootDIR . 'setting/default-setting.php';
      // ID của bảng tính và tên của trang tính bạn muốn thao tác
      if(isset($spreadsheetId)){
        $this->spreadsheetId = $spreadsheetId; 
      }else{
        $this->spreadsheetId = $setting['spreadsheetId']['WhiteList'];
      }
    }

  
    public function getRange($range){
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
        $values = $response->getValues();
        return $values;
    }

    public function updateRange($range,$values){
        // Ghi dữ liệu vào bảng tính
        $body = new Google_Service_Sheets_ValueRange([
          'values' => $values
        ]);
        $params = [
          'valueInputOption' => 'RAW'
        ];
        $result = $this->service->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);

        //printf("%d cells updated.\n", $result->getUpdatedCells());
        return $result->getUpdatedCells();
    }

    public function append($values,$range=''){
      $body = new Sheets\ValueRange([
        'values' => $values
      ]);
      
      $params = [
          'valueInputOption' => 'RAW', // RAW or USER_ENTERED
          'insertDataOption' => 'INSERT_ROWS'
      ];
      if(isset($range)){
        $range = $range . '!';
      }
      $result = $this->service->spreadsheets_values->append($this->spreadsheetId, $range .'A2', $body, $params);
    }
}
?>