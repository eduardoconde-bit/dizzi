<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require './vendor/autoload.php';

use Dizzi\Database\Database;

$db = new Database();
$db = $db->getConnection();
