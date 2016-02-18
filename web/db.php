<?php

$db_host = "localhost";
$db_user = "root";
$db_passwd = "";
$db_dbname = "aprs";


$mysqli = new mysqli($db_host, $db_user, $db_passwd, $db_dbname);
//面向对象的昂视屏蔽了连接产生的错误，需要通过函数来判断
if(mysqli_connect_error()){
	echo mysqli_connect_error();
}

//$mysqli->query("SET NAMES utf8");

session_start();

?>
