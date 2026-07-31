<?php

class GenericSelector
{
    protected $db;
    private string $table;
    private ?array $allowedFields;

    /**
     * GenericSelector constructor.
     *
     * @param PDO $db
     * @param string $table
     * @param array|null $allowedFields
     * @throws Exception
     */
    public function __construct($db, string $table, ?array $allowedFields = null)
    {
        $this->db = $db;
        $this->table = $table;

        if ($allowedFields === ['*'] || $allowedFields == null) {
            $this->allowedFields = $this->describeTable();
        } else {
            $this->allowedFields = $allowedFields;
        }
    }

    /**
     * Получает список всех полей таблицы через DESCRIBE.
     *
     * @return array
     * @throws Exception
     */
    private function describeTable(): array
    {
        $stmt = $this->db->prepare("DESCRIBE `{$this->table}`");
        if (!$stmt->execute()) {
            throw new Exception("Не удалось DESCRIBE таблицу `{$this->table}`");
        }

        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($columns) || empty($columns)) {
            throw new Exception("Не удалось получить список полей таблицы `{$this->table}`");
        }

        return $columns;
    }

    /**
     * Выполняет SELECT-запрос с фильтрацией и сортировкой.
     *
     * @param array $criteria
     * @param array $selectFields
     * @param string|null $orderBy
     * @param string $orderDirection
     * @param int|null $limit
     * @return array
     * @throws Exception
     */
    public function select(
        array $criteria = [],
        array $selectFields = [],
        ?string $orderBy = null,
        string $orderDirection = 'ASC',
        ?int $limit = null
    ): array {
        if (empty($selectFields)) {
            $selectFields = $this->allowedFields ?? $this->describeTable();
        } elseif ($this->allowedFields !== null) {
            $selectFields = array_intersect($selectFields, $this->allowedFields);
        }

        if (empty($selectFields)) {
            throw new Exception("Нет допустимых полей для выборки.");
        }

        $columns = implode(', ', array_map(fn($f) => "`$f`", $selectFields));
        [$whereClause, $params] = $this->buildWhereClause($criteria);

        $sql = "SELECT {$columns} FROM `{$this->table}`{$whereClause}";

        if ($orderBy !== null && ($this->allowedFields === null || in_array($orderBy, $this->allowedFields, true))) {
            $direction = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `$orderBy` $direction";
        }

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $limit;
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $param => $value) {
            $stmt->bindValue(
                is_string($param) && strpos($param, ':') === false ? ":$param" : $param,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Собирает WHERE-условие и параметры.
     *
     * @param array $where
     * @return array
     * @throws Exception
     */
    private function buildWhereClause(array $where): array
    {
        $conditions = [];
        $params = [];

        foreach ($where as $field => $value) {
            if ($this->allowedFields !== null && !in_array($field, $this->allowedFields, true)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $operator => $val) {
                    $paramKey = "{$field}_" . strtolower($operator);
                    $operator = strtoupper(trim($operator));

                    if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>=', 'LIKE'], true)) {
                        throw new Exception("Недопустимый оператор: $operator");
                    }

                    $conditions[] = "`$field` $operator :$paramKey";
                    $params[$paramKey] = $val;
                }
            } else {
                $conditions[] = "`$field` = :$field";
                $params[$field] = $value;
            }
        }

        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }

        return [$whereClause, $params];
    }
}
