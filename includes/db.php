<?php
/**
 * Класс для работы с базой данных
 */
class Database
{
    private $conn;

    /**
     * Конструктор класса, устанавливает соединение с БД
     */
    public function __construct()
    {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $this->conn->set_charset("utf8");

            if ($this->conn->connect_error) {
                throw new Exception("Ошибка подключения к базе данных: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            echo "Ошибка: " . $e->getMessage();
            die();
        }
    }

    /**
     * Выполняет SQL запрос
     * 
     * @param string $sql SQL запрос
     * @return mixed Результат запроса
     */
    public function query($sql)
    {
        $result = $this->conn->query($sql);
        if (!$result) {
            throw new Exception("Ошибка выполнения запроса: " . $this->conn->error);
        }
        return $result;
    }

    /**
     * Подготавливает и выполняет параметризованный запрос
     * 
     * @param string $sql SQL запрос с плейсхолдерами
     * @param string $types Типы параметров (i для int, s для string, d для double, b для blob)
     * @param array $params Массив параметров
     * @return mixed Результат запроса
     */
    public function prepareAndExecute($sql, $types, $params)
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Получает все строки результата запроса
     * 
     * @param string $sql SQL запрос
     * @return array Ассоциативный массив с результатами запроса
     */
    public function getAll($sql)
    {
        $result = $this->query($sql);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Получает одну строку результата запроса
     * 
     * @param string $sql SQL запрос
     * @return array|null Ассоциативный массив с результатом или null
     */
    public function getRow($sql)
    {
        $result = $this->query($sql);
        return $result->fetch_assoc();
    }

    /**
     * Получает одно значение из результата запроса
     * 
     * @param string $sql SQL запрос
     * @return mixed Значение или null
     */
    public function getValue($sql)
    {
        $result = $this->query($sql);
        $row = $result->fetch_row();
        return $row ? $row[0] : null;
    }

    /**
     * Возвращает ID последней вставленной записи
     * 
     * @return int ID записи
     */
    public function getLastInsertId()
    {
        return $this->conn->insert_id;
    }

    /**
     * Экранирует специальные символы в строке
     * 
     * @param string $str Строка для экранирования
     * @return string Экранированная строка
     */
    public function escapeString($str)
    {
        return $this->conn->real_escape_string($str);
    }

    /**
     * Закрывает соединение с БД
     */
    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
