<?php
require_once __DIR__ . '/Config.php';

class TelegramBot {
    private $token;
    private $apiUrl;
    private $configClass;

    public function __construct($configClass = 'Config') {
        $this->configClass = $configClass;
        $this->token = $configClass::get('BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
    }

    public function send($chat_id, $type, $media = null, $caption = null, $keyboard = null) {
        $data = ['chat_id' => $chat_id];

        if ($type === 'text') {
            $data['text'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendMessage';
        } elseif ($type === 'photo') {
            $data['photo'] = $media;
            if ($caption) $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendPhoto';
        } elseif ($type === 'video') {
            $data['video'] = $media;
            if ($caption) $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendVideo';
        } elseif ($type === 'audio') {
            $data['audio'] = $media;
            if ($caption) $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendAudio';
        } elseif ($type === 'voice') {
            $data['voice'] = $media;
            if ($caption) $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendVoice';
        } elseif ($type === 'document') {
            $data['document'] = $media;
            if ($caption) $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendDocument';
        } else {
            return false;
        }

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function deleteMessage($chat_id, $message_id) {
        $data = ['chat_id' => $chat_id, 'message_id' => $message_id];
        $url = $this->apiUrl . 'deleteMessage';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function detectMessageType($message) {
        $result = ['type' => null, 'content' => null, 'caption' => null];
        if (isset($message['text'])) {
            $result['type'] = 'text';
            $result['content'] = $message['text'];
            $result['caption'] = $message['text'];
        } elseif (isset($message['photo'])) {
            $result['type'] = 'photo';
            $result['content'] = $message['photo'][count($message['photo']) - 1]['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['video'])) {
            $result['type'] = 'video';
            $result['content'] = $message['video']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['audio'])) {
            $result['type'] = 'audio';
            $result['content'] = $message['audio']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['voice'])) {
            $result['type'] = 'voice';
            $result['content'] = $message['voice']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['document'])) {
            $result['type'] = 'document';
            $result['content'] = $message['document']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        }
        return $result;
    }
}