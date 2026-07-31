<?php

/**
 * Класс GenericUpdater
 *
 * Позволяет безопасно выполнять массовое обновление, добавление и удаление записей в таблице БД,
 * с автоматическим контролем допустимых полей и возможностью удаления отсутствующих записей.
 */
class GenericUpdater
{
    /** @var PDO */
    protected $db;

    /** @var string */
    protected $table;

    /** @var array */
    protected $allowedFields;

    /** @var bool */
    protected $deleteMissing;

    /**
     * Конструктор
     *
     * @param PDO    $db             Подключение к базе данных
     * @param string $table          Имя таблицы
     * @param array  $allowedFields  Список разрешённых полей для операций
     * @param bool   $deleteMissing  Удалять строки, отсутствующие в переданных данных
     */
    public function __construct($db, $table, array $allowedFields, $deleteMissing = false)
    {
        $this->db = $db;
        $this->table = $table;
        $this->allowedFields = $allowedFields;
        $this->deleteMissing = $deleteMissing;
    }

    /**
     * Массовое обновление данных.
     *
     * @param string|array $jsonData   JSON строка или массив данных для обновления
     * @param string       $primaryKey Поле уникального ключа (по умолчанию 'id')
     *
     * @return array Массив с типом результата и сообщением
     */
    public function updateData($jsonData, $primaryKey = 'id')
    {
        if (is_string($jsonData)) {
            $data = json_decode($jsonData, true);
            if (!is_array($data)) {
                return ['type' => 'error', 'message' => 'Неверный JSON формат'];
            }
        } else {
            $data = $jsonData;
        }

        // Приведение к массиву записей
        if (array_keys($data) !== range(0, count($data) - 1)) {
            $data = [$data];
        }

        $filteredData = [];
        $newPrimaryKeys = [];

        foreach ($data as $row) {
            $filtered = array_intersect_key($row, array_flip($this->allowedFields));
            if (isset($row[$primaryKey])) {
                $newPrimaryKeys[] = $row[$primaryKey];
                $filtered[$primaryKey] = $row[$primaryKey];
            }
            $filteredData[] = $filtered;
        }

        if (empty($filteredData)) {
            return ['type' => 'error', 'message' => 'Нет данных для обновления'];
        }

        // Формирование полей для запроса
        $fields = array_keys($filteredData[0]);
        $fieldList = implode(", ", array_map(fn($f) => "`{$f}`", $fields));
        $placeholders = implode(", ", array_map(fn($f) => ":{$f}", $fields));

        $updateFields = [];
        foreach ($fields as $field) {
            if ($field === $primaryKey) continue;
            $updateFields[] = "`{$field}` = VALUES(`{$field}`)";
        }
        $updateClause = implode(", ", $updateFields);

        // Подготовка SQL
        $sql = "INSERT INTO `{$this->table}` ({$fieldList})
                VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$updateClause}";

        $stmt = $this->db->prepare($sql);

        try {
            foreach ($filteredData as $row) {
                $stmt->execute($row);
            }

            $deletedCount = 0;

            // Удаление записей, если требуется
            if ($this->deleteMissing && !empty($newPrimaryKeys)) {
                $existingSql = "SELECT `{$primaryKey}` FROM `{$this->table}`";
                $existingKeys = $this->db->query($existingSql)->fetchAll(PDO::FETCH_COLUMN);
                $toDelete = array_diff($existingKeys, $newPrimaryKeys);

                if (!empty($toDelete)) {
                    $in = implode(',', array_fill(0, count($toDelete), '?'));
                    $deleteSql = "DELETE FROM `{$this->table}` WHERE `{$primaryKey}` IN ({$in})";
                    $delStmt = $this->db->prepare($deleteSql);
                    $delStmt->execute(array_values($toDelete));
                    $deletedCount = $delStmt->rowCount();
                }
            }

            return [
                'type' => 'success',
                'message' => count($filteredData) . ' записей обновлено/добавлено, удалено: ' . $deletedCount
            ];
        } catch (PDOException $e) {
            return [
                'type' => 'error',
                'message' => 'Ошибка при обновлении данных: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Обновление одной строки по значению ключа.
     *
     * @param array  $data        Данные для обновления
     * @param string $primaryKey  Имя первичного ключа
     * @param mixed  $primaryValue Значение первичного ключа
     *
     * @return array Результат выполнения операции
     */
    public function updateRowByKey(array $data, string $primaryKey, $primaryValue)
    {
        $filtered = array_intersect_key($data, array_flip($this->allowedFields));

        if (empty($filtered)) {
            return ['type' => 'error', 'message' => 'Нет допустимых полей для обновления'];
        }

        $setClause = implode(', ', array_map(fn($f) => "`$f` = :$f", array_keys($filtered)));
        $sql = "UPDATE `{$this->table}` SET {$setClause} WHERE `{$primaryKey}` = :__primary";

        $stmt = $this->db->prepare($sql);
        $filtered['__primary'] = $primaryValue;

        try {
            $stmt->execute($filtered);
            return [
                'type' => 'success',
                'message' => $stmt->rowCount() . ' строк(а) обновлено'
            ];
        } catch (PDOException $e) {
            return [
                'type' => 'error',
                'message' => 'Ошибка при обновлении: ' . $e->getMessage()
            ];
        }
    }
}

?>
