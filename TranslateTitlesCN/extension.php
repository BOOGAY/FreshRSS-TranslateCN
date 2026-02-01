<?php
require_once(__DIR__ . '/lib/TranslateController.php');
require_once(__DIR__ . '/lib/TranslationService.php');

class TranslateExtension extends Minz_Extension {
    public function init() {
        $this->registerHook('entry_before_insert', array($this, 'translateEntry'));
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            FreshRSS_Context::$user_conf->TranslateService = Minz_Request::param('TranslateService', 'google');
            FreshRSS_Context::$user_conf->TargetLanguage = Minz_Request::param('TargetLanguageCode', 'en');
            FreshRSS_Context::$user_conf->TranslateTitles = Minz_Request::param('TranslateTitles', array());
            FreshRSS_Context::$user_conf->DeeplxApiUrl = Minz_Request::param('DeeplxApiUrl');
            FreshRSS_Context::$user_conf->LibreApiUrl = Minz_Request::param('LibreApiUrl');
            FreshRSS_Context::$user_conf->LibreApiKey = Minz_Request::param('LibreApiKey');
            FreshRSS_Context::$user_conf->save();
        }
    }

    public function translateEntry($entry) {
        // 关键：防止同步抓取时大量 PHP 进程超时
        @set_time_limit(60);

        $feedId = $entry->feed()->id();
        $targetLang = FreshRSS_Context::$user_conf->TargetLanguage ?? 'zh';
        
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles[$feedId]) && 
            FreshRSS_Context::$user_conf->TranslateTitles[$feedId] == '1') {
            
            $serviceType = FreshRSS_Context::$user_conf->TranslateService ?? 'google';
            $service = new TranslateExtensionService($serviceType);
            $controller = new TranslateExtensionController();

            // 1. 标题：原文 | 译文
            $oldTitle = $entry->title();
            $newTitle = $controller->translate($oldTitle, $targetLang);
            if (!empty($newTitle) && $newTitle !== $oldTitle) {
                $entry->_title($oldTitle . ' | ' . $newTitle);
            }

            // 2. 正文：分段对照
            $oldContent = $entry->content();
            if (!empty($oldContent)) {
                $bilingualContent = $service->translateBilingual($oldContent, $targetLang);
                if (!empty($bilingualContent)) {
                    $entry->_content($bilingualContent);
                }
            }
        }
        return $entry;
    }
}