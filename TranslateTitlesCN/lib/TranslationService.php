<?php
class TranslateExtensionService {
    private $serviceType;
    private $deeplxBaseUrl;
    private $googleBaseUrl;
    private $libreBaseUrl;
    private $libreApiKey;

    public function __construct($serviceType) {
        $this->serviceType = $serviceType;
        $this->deeplxBaseUrl = FreshRSS_Context::$user_conf->DeeplxApiUrl;
        $this->googleBaseUrl = 'https://translate.googleapis.com/translate_a/single';
        $this->libreBaseUrl = FreshRSS_Context::$user_conf->LibreApiUrl;
        $this->libreApiKey = FreshRSS_Context::$user_conf->LibreApiKey;
    }

    public function translateBilingual($html, $targetLang = 'zh') {
        if (empty($html)) return $html;

        // 利用正则拆分块级元素，保留原始 HTML 结构
        $paragraphs = preg_split('/(<(?:p|div|li)[^>]*>.*?<\/(?:p|div|li)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        $result = '';
        $controller = new TranslateExtensionController();
        $translatedCount = 0;

        foreach ($paragraphs as $para) {
            // 性能保护：单篇文章最多翻译 40 个段落
            if ($translatedCount > 40) {
                $result .= $para;
                continue;
            }

            $textOnly = trim(strip_tags($para));
            if (mb_strlen($textOnly, 'UTF-8') < 3) {
                $result .= $para;
                continue;
            }

            $translated = $controller->translate($textOnly, $targetLang);

            if (!empty($translated) && $translated !== $textOnly) {
                if (strpos($para, '<') === 0) {
                    // 样式优化：译文使用灰色、较小字号并增加上下间距
                    $result .= $para . '<div style="color: #888; font-style: italic; font-size: 0.9em; margin: 4px 0 12px 0; line-height: 1.5;">' . $translated . '</div>';
                } else {
                    $result .= $para . ' <small style="color: #888;">(' . $translated . ')</small> ';
                }
                $translatedCount++;
            } else {
                $result .= $para;
            }
        }
        return $result;
    }

    public function translate($text, $targetLang = 'zh') {
        switch ($this->serviceType) {
            case 'deeplx': return $this->translateWithDeeplx($text, $targetLang);
            case 'libre': return $this->translateWithLibre($text, $targetLang);
            default: return $this->translateWithGoogle($text, $targetLang);
        }
    }

    private function translateWithLibre($text, $targetLang) {
        $apiUrl = rtrim($this->libreBaseUrl, '/') . '/translate';
        $postData = json_encode(['q' => $text, 'source' => 'auto', 'target' => $targetLang, 'format' => 'text', 'api_key' => $this->libreApiKey]);
        $options = ['http' => ['header' => "Content-Type: application/json\r\n", 'method' => 'POST', 'content' => $postData, 'timeout' => 5]];
        $result = @file_get_contents($apiUrl, false, stream_context_create($options));
        $response = json_decode($result, true);
        return $response['translatedText'] ?? '';
    }

    private function translateWithGoogle($text, $targetLang) {
        $url = $this->googleBaseUrl . '?' . http_build_query(['client' => 'gtx', 'sl' => 'auto', 'tl' => $targetLang, 'dt' => 't', 'q' => $text]);
        $options = ['http' => ['timeout' => 5]];
        $result = @file_get_contents($url, false, stream_context_create($options));
        $response = json_decode($result, true);
        return $response[0][0][0] ?? '';
    }

    private function translateWithDeeplx($text, $targetLang) {
        $langMap = ['pt' => 'PT-BR', 'en' => 'EN-US', 'zh' => 'ZH'];
        $deeplTargetLang = $langMap[strtolower($targetLang)] ?? strtoupper($targetLang);
        $postData = json_encode(['text' => $text, 'source_lang' => 'auto', 'target_lang' => $deeplTargetLang]);
        $options = ['http' => ['header' => "Content-Type: application/json\r\n", 'method' => 'POST', 'content' => $postData, 'timeout' => 5]];
        $result = @file_get_contents($this->deeplxBaseUrl, false, stream_context_create($options));
        $response = json_decode($result, true);
        return $response['data'] ?? '';
    }
}