<?php
// 1. Primary CareSync Database Connection (ap-southeast-1)
$host     = "aws-0-ap-southeast-1.pooler.supabase.com"; 
$port     = "5432";
$database = "postgres";
$user     = "postgres.djnepquwxdmkaoyepzdo";              
$password = "caresync12345@"; 

$conn_str = "host={$host} port={$port} dbname={$database} user={$user} password={$password} sslmode=require";
$dbconn = pg_connect($conn_str);

if (!$dbconn) {
    die("CareSync Database connection failed: " . pg_last_error());
}
pg_query($dbconn, "SET TIME ZONE 'Asia/Colombo'");

// 2. Secondary PharmaConnect Database Connection (ap-northeast-2)
$pharma_host     = "aws-0-ap-northeast-2.pooler.supabase.com";
$pharma_port     = "5432"; // or 6543 if using pooler port
$pharma_database = "postgres";
$pharma_user     = "postgres.gfbnuizhplqjyebnqgxx";
$pharma_password = "pharmaconnect12345@"; // The database password you chose when creating this project

$pharma_conn_str = "host={$pharma_host} port={$pharma_port} dbname={$pharma_database} user={$pharma_user} password={$pharma_password} sslmode=require";
$pharma_dbconn = @pg_connect($pharma_conn_str);

// DB Wrapper definitions
if (!class_exists('DBWrapper')) {
    class DBWrapper {
        private $conn;
        public function __construct($c) { $this->conn = $c; }

        public function query($sql) {
            if (!$this->conn) return null;
            $res = pg_query($this->conn, $sql);
            if (!$res) {
                die("Query error: " . pg_last_error($this->conn));
            }
            return new ResultWrapper($res);
        }

        public function real_escape_string($str) {
            return $this->conn ? pg_escape_string($this->conn, $str) : addslashes($str);
        }
    }
}

if (!class_exists('ResultWrapper')) {
    class ResultWrapper {
        private $res;
        public $num_rows;

        public function __construct($r) {
            $this->res = $r;
            $this->num_rows = is_resource($r) ? pg_num_rows($r) : 0;
        }

        public function fetch_assoc() {
            return $this->res ? pg_fetch_assoc($this->res) : null;
        }

        public function data_seek($row) {
            if ($this->num_rows > 0) {
                pg_result_seek($this->res, $row);
            }
        }
    }
}

$conn = new DBWrapper($dbconn);
$pharma_conn = $pharma_dbconn ? new DBWrapper($pharma_dbconn) : null;
?>