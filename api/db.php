<?php
// Database configuration and connection
class Database {
    private $host = 'localhost';
    private $db_name = 'hospital_labacl';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $pdo;

    public function connect() {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return $this->pdo;
    }
}

// Global database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        $database = new Database();
        $db = $database->connect();
    }
    return $db;
}
?>
