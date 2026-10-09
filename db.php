<?php
// Supabase Connection Credentials
$host     = "aws-0-ap-southeast-1.pooler.supabase.com"; 
$port     = "5432";
$database = "postgres";
$user     = "postgres.djnepquwxdmkaoyepzdo";              
$password = "caresync12345@"; // Decoded from %40                       

// Construct connection string requiring SSL
$conn_str = "host={$host} port={$port} dbname={$database} user={$user} password={$password} sslmode=require";
$dbconn = pg_connect($conn_str);

if (!$dbconn) {
    die("Database connection failed: " . pg_last_error());
}

pg_query($dbconn, "SET TIME ZONE 'Asia/Colombo'");

// Wrapper class so your existing index.php and login.php queries work without modification
class DBWrapper {
    private $conn;
    public function __construct($c) { $this->conn = $c; }

    public function query($sql) {
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            die("Query error: " . pg_last_error($this->conn));
        }
        return new ResultWrapper($res);
    }

    public function real_escape_string($str) {
        return pg_escape_string($this->conn, $str);
    }
}

class ResultWrapper {
    private $res;
    public $num_rows;

    public function __construct($r) {
        $this->res = $r;
        $this->num_rows = pg_num_rows($r);
    }

    public function fetch_assoc() {
        return pg_fetch_assoc($this->res);
    }

    public function data_seek($row) {
        if ($this->num_rows > 0) {
            pg_result_seek($this->res, $row);
        }
    }
}

$conn = new DBWrapper($dbconn);
?>