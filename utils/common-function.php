<?php 
class UniqueArray {
    private $hashMap = [];

    public function insert($element) {
        // Nếu phần tử đã tồn tại trong mảng, không làm gì cả
        if (isset($this->hashMap[$element])) {
            return;
        }
        // Thêm phần tử vào mảng
        $this->hashMap[$element] = true;
    }
    public function includes($element){
        if (isset($this->hashMap[$element])) {
            return true;
        }
        return false;
    }
    public function toArray() {
        // Trả về danh sách các phần tử duy nhất
        return array_keys($this->hashMap);
    }
}
/**
 * So sánh hai link có trùng host ko
 */
function are_urls_same_host($url1, $url2) {
    // Phân tích URL để lấy ra host
    $host1 = parse_url($url1, PHP_URL_HOST);
    $host2 = parse_url($url2, PHP_URL_HOST);

    // Kiểm tra nếu một trong hai host là null, có nghĩa là URL không hợp lệ
    if ($host1 === null || $host2 === null) {
        return false;
    }

    // So sánh hai host không phân biệt chữ hoa chữ thường
    return strcasecmp($host1, $host2) === 0;
}

/**
 * tách các url có trong chuỗi
 */
function extract_urls_from_text($text) {
    $pattern = '(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})'; 
    preg_match_all($pattern, $text, $matches);
    return $matches[0];
}

function extractLinks($html) {
    if (empty($html)) {
        return [];
    }
    $pattern =  '(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})'; 
    // Tạo một đối tượng DOM từ chuỗi HTML
    $dom = new DOMDocument();
    // Tắt thông báo lỗi khi phân tích HTML
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);

    // Lấy tất cả các liên kết từ các thẻ <a>
    $links  =new UniqueArray();
    $anchors = $dom->getElementsByTagName('a');
    // Throw an exception with a specific error code
    foreach ($anchors as $anchor) {
        $tmp = $anchor->getAttribute('href');
        if(preg_match($pattern,$tmp)){
            $links->insert($tmp);
        }
    }

    // Lặp qua tất cả các phần tử văn bản
    $textElements = $dom->getElementsByTagName('*');
    foreach ($textElements as $textElement) {
        // Lấy văn bản của phần tử và kiểm tra xem có chứa liên kết không
        $textContent = $textElement->textContent;
        if (preg_match_all($pattern, $textContent, $matches)) {
            // Nếu có liên kết trong văn bản, thêm chúng vào mảng liên kết
            foreach ($matches[0] as $link) {
                $links->insert( $link);
            }
        }
    }
    // Trả về mảng chứa tất cả các liên kết
    return $links->toArray();
}
/**
 * Lấy h hiện tại
 */
function getCurrentDateTime() {
    $dateTime = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')); // Đặt múi giờ nếu cần
    return $dateTime->format('Y-m-d H:i:s');
}
?>


