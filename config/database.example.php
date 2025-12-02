<?php
class Database {
    private $host = 'YOUR_DB_HOST';
    private $db_name = 'YOUR_DB_NAME';
    private $username = 'YOUR_DB_USER';
    private $password = 'YOUR_DB_PASSWORD';
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

function db() {
    $database = new Database();
    return $database->getConnection();
}
?>
