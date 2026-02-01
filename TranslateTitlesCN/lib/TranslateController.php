<?php
require_once(__DIR__ . '/TranslationService.php');

class TranslateExtensionController {
    public function translate($text, $targetLang = 'zh') {
        if (empty($text)) return '';
        $serviceType = FreshRSS_Context::$user_conf->TranslateService ?? 'google';
        $translationService = new TranslateExtensionService($serviceType);
        
        try {
            // 首选尝试
            $translatedText = $translationService->translate($text, $targetLang);
            if (!empty($translatedText)) return $translatedText;

            // 失败时仅在非Google模式下尝试一次Google降级，降低并发压力
            if ($serviceType !== 'google') {
                $fallbackService = new TranslateExtensionService('google');
                return $fallbackService->translate($text, $targetLang);
            }
        } catch (Exception $e) {
            return $text; // 快速失败返回
        }
        return $text;
    }
}