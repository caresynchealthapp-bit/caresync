<?php
// ==========================================
// 1. PRIMARY: CareSync Database (ap-southeast-1)
// ==========================================
$host     = "aws-0-ap-southeast-1.pooler.supabase.com"; 
$port     = "5432";
$database = "postgres";
$user     = "postgres.djnepquwxdmkaoyepzdo";              
$password = "caresync12345@";

$conn_str = "host={$host} port={$port} dbname={$database} user={$user} password={$password} sslmode=require";
$dbconn = @pg_connect($conn_str);

if (!$dbconn) {
    die("CareSync Database connection failed: " . pg_last_error());
}

pg_query($dbconn, "SET TIME ZONE 'Asia/Colombo'");

// ==========================================
// 2. SECONDARY: PharmaConnect Database (ap-northeast-1)
// ==========================================
$pharma_host     = "aws-0-ap-northeast-1.pooler.supabase.com"; 
$pharma_port     = "5432";
$pharma_database = "postgres";
$pharma_user     = "postgres.juobkaftyrzlxbviudpy";              
$pharma_password = "pharmaconnect12345@"; // Replace with your actual PharmaConnect database password if different

$pharma_conn_str = "host={$pharma_host} port={$pharma_port} dbname={$pharma_database} user={$pharma_user} password={$pharma_password} sslmode=require";
$pharma_dbconn = @pg_connect($pharma_conn_str);

if ($pharma_dbconn) {
    pg_query($pharma_dbconn, "SET TIME ZONE 'Asia/Colombo'");
}

// ==========================================
// 3. DATABASE WRAPPER CLASSES
// ==========================================
if (!class_exists('DBWrapper')) {
    class DBWrapper {
        private $conn;
        public function __construct($c) { 
            $this->conn = $c; 
        }

        public function query($sql) {
            if (!$this->conn) return null;
            $res = @pg_query($this->conn, $sql);
            if (!$res) {
                return null;
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
            $this->num_rows = ($r && is_resource($r)) ? pg_num_rows($r) : 0;
        }

        public function fetch_assoc() {
            return ($this->res && is_resource($this->res)) ? pg_fetch_assoc($this->res) : null;
        }

        public function data_seek($row) {
            if ($this->res && is_resource($this->res) && $this->num_rows > 0) {
                @pg_result_seek($this->res, $row);
            }
        }
    }
}

// Global connections available to index.php, login.php, register.php
$conn = new DBWrapper($dbconn);
$pharma_conn = $pharma_dbconn ? new DBWrapper($pharma_dbconn) : null;
?>