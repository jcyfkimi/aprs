<?php

include "db.php";

date_default_timezone_set( 'Asia/Shanghai');

if (isset($_REQUEST["call"]))  
	$_SESSION["call"]=$_REQUEST["call"];
if (isset($_REQUEST["sn"]))  
	$_SESSION["sn"]=$_REQUEST["sn"];
if (isset($_REQUEST["pass"]))  
	$_SESSION["pass"]=$_REQUEST["pass"];

$call=$_SESSION["call"];
$sn=$_SESSION["sn"];
$pass=$_SESSION["pass"];

if (isset($_REQUEST["cmd"]))  
	$cmd=$_REQUEST["cmd"];
else $cmd="";

if($cmd!="") {
	$q = "insert into ykcmd (cmdtm,`call`,sn,pass,cmd,sendtm,replytm) values (now(),?,?,?,?,'0000-00-00 00:00:00','0000-00-00 00:00:00')";
        $stmt = $mysqli->prepare($q);
        if(!$stmt) {
                echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
                exit;
        }
        $stmt->bind_param("ssss",$call,$sn,$pass,$cmd);
        $stmt->execute();
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<style type="text/css">
		body, html{width: 100%;height: 100%;margin:5;font-family:"微软雅黑";}
	</style>
	<title>远程控制网关</title>
</head>
<body>
<h2>远程网关</h2>
<form action=index.php method=POST>
<?php
echo "<table>";
echo "<tr><td>呼号</td><td>";
echo "<input name=call value=\"".$call."\"></td></tr>";

echo "<tr><td>序列号</td><td>";
echo "<input name=sn value=\"".$sn."\"></td></tr>";

echo "<tr><td>密码</td><td>";
echo "<input name=pass type=password value=\"".$pass."\"></td></tr>";

echo "<tr><td>指令</td><td>";
echo "<input name=cmd value=\"".$cmd."\"></td></tr>";

echo "<tr><td colspan=2 align=center><a href=index.php>刷新显示</a> &nbsp;&nbsp; ";
echo "<input type=submit name=send value=\"发送命令\"></td></tr>";
echo "</table>";

 echo "<h3>最后的命令(相同的呼号和密码)</h3>";
        $q="select `call`,sn,cmd,reply,cmdtm,sendtm,replytm from ykcmd where `call`=? and pass =? order by cmdtm desc limit 10";
	$stmt=$mysqli->prepare($q);
        if(!$stmt) {
                echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
                exit;
        }
        $stmt->bind_param("ss",$call,$pass);
        $stmt->execute();
        $stmt->bind_result($rcall,$rsn,$rcmd,$rreply,$rcmdtm,$rsendtm,$rreplytm);
        $stmt->store_result();
        echo "<table border=1 cellspacing=0><tr><th>呼号</th>";
        echo "<th>序列号</th><th>时间</th><th>发送时间</th><th>应答时间</ht><th>命令</th><th>应答</th></tr>";
        while($stmt->fetch()) {
                echo "<tr><td>";
                echo $rcall;
                echo "</td><td align=right>";
                echo $rsn;
                echo "</td><td align=right>";
                echo $rcmdtm;
                echo "</td><td align=right>";
                echo $rsendtm;
                echo "</td><td align=right>";
                echo $rreplytm;
                echo "</td><td>";
                echo $rcmd;
                echo "</td><td>";
                echo $rreply;
                echo "</td></tr>\n";
        }
        echo "</table>";

?>
</body>
