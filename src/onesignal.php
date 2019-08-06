<?php

class OneSignalAPI {
    private $appId;

    function __construct(string $appId) {
        $this->appId = $appId;
    }

    function sendMessage(string $headings, string $content, array $fields = null, array $data = null) {
        if (!is_array($fields))
            $fields = array();

        // configure fields
        $fields['app_id'] = $this->appId;
        $fields['headings'] = array(
            "en" => $headings
        );
        $fields['contents'] = array(
            "en" => $content
        );

        // message data
        if (is_array($data))
            $fields['data'] = $data;
        
        // encode fields
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    function sendToId(string $playerId, string $type, string $title, string $message) {
        $data = array(
            'type' => $type,
            'message' => $message
        );
        return $this->sendMessage($title, $message, array(
            'include_player_ids' => array($playerId)
        ), $data);
    }
}

?>
