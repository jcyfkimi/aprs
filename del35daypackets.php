<?php

date_default_timezone_set("Asia/Shanghai");
include "web/db.php";

$q="delete from aprspacket where tm<=date_sub(now(), interval 35 day)";
$result = $mysqli->query($q);
?>
