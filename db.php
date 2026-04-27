<?php
if (!class_exists('Database')) {
    class Database {
    private $host = "localhost";
    private $db_name = "tesda_auto_mechanic";
    private $username = "root";
    private $password = "";
    public $conn = null;

    /**
     * Create and return a PDO connection or null on failure.
     * @return \PDO|null
     */
    public function getConnection(): ?\PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }
        try {
            $this->conn = new \PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $this->conn->exec("set names utf8");
        } catch(\PDOException $exception) {
            // Silent fail - return null, don't echo
            $this->conn = null;
        }
        return $this->conn;
    }
    }
}
?>