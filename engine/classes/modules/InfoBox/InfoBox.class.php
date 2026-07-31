<?php

if (!defined('FOXXEY')) {
    die("Hacking attempt!");
}

class InfoBox extends Module
{
    protected $db;
    protected $logger;
    private $userTag;

    /** @var GenericSelector */
    private $selector;

    public function __construct($db, $logger, $tag)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->tag = $tag;
        $this->selector = new GenericSelector($this->db, 'infobox');
    }

public function getInfoBox()
{
    // Если тэг не "admin", применяем фильтрацию
    $criteria = [];
    if ($this->tag !== "admin") {
        $criteria['group_name'] = $this->tag;
    }

    // Добавляем нужные поля для выборки
    $selectFields = [
        "start_timestamp", 
        "end_timestamp", 
        "title", 
        "text", 
        "image", 
        "button_text", 
        "button_url", 
        "group_name"
    ];

    try {
        // Получаем данные с помощью GenericSelector
        $rows = $this->selector->select($criteria, $selectFields);

        $infoBoxes = [];

        foreach ($rows as $info) {
            $infoBoxes[] = [
                "group_name"      => $info['group_name'],
                "start_timestamp" => (int)$info['start_timestamp'],
                "end_timestamp"   => (int)$info['end_timestamp'],
                "title"           => $info['title'],
                "text"            => $info['text'],
                "image"           => $info['image'],
                "button_text"     => $info['button_text'],
                "button_url"      => $info['button_url']
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($infoBoxes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        $this->logger->error("Exception in InfoBox::getInfoBox(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "error" => "Database execution error"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

}
