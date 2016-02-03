<?php

include "db.php";

if (isset($_REQUEST["new"])) {
	$cmd="new";
	header("refresh: 3;");
} else if (isset($_REQUEST["today"])) {
	$cmd="today";
	header("refresh: 60;");
} else if (isset($_REQUEST["call"])) {
	$cmd="call";
	$call=$_REQUEST["call"];
	header("refresh: 60;");
} else if (isset($_REQUEST["stats"])) {
	$cmd="stats";
	header("refresh: 60;");
} else if (isset($_REQUEST["about"])) {
	$cmd="about";
} else {
	$cmd="new";
	header("refresh: 3;");
}

?>

<html><head><meta http-equiv="Content-Type" content="text/html; charset=gb2312" />
	<title>APRS relay</title>
</head>

<body bgcolor=#dddddd>

<?php
echo "<a href=index.php>最新数据包</a> <a href=index.php?today>今天数据包</a> <a href=index.php?stats>数据包统计</a> ";

$q="select count(distinct(`call`)) from aprspacket where tm>=curdate()";
$result = $mysqli->query($q);
$r=$result->fetch_array();
echo $r[0]." 呼号发送了 ";
$q="select count(*) from aprspacket where tm>=curdate()";
$result = $mysqli->query($q);
$r=$result->fetch_array();
echo $r[0]." 数据包 ";

echo " <a href=index.php?about>关于本站</a><p>";
if ($cmd=="new") {
	echo "<h3>最新收到的APRS数据包</h3>";
	$q="select tm,`call`,raw from aprspacket where tm>=curdate() order by tm desc limit 10";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th>时间</th><th>呼号</th><th>APRS Packet</th><th>地图</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo $r[0];
        	echo "</td><td>";
        	echo "<a href=index.php?call=$r[1]>$r[1]</a>";
        	echo "</td><td>";
        	echo $r[2];
        	echo "</td><td>";
		echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$r[1]."&timerange=43200&tail=43200\" target=_blank>aprs.fi</a>";
        	echo "</td></tr>\n";
	}
	echo "</table>\n";
	exit(0);
}

if ($cmd=="today") {
	echo "<h3>今天收到的APRS数据包</h3>";
	if(isset($_REQUEST["c"]))
		$q = "select `call`, count(*) c from aprspacket where tm>curdate() group by `call` order by c desc";
	else
		$q = "select `call`, count(*) from aprspacket where tm>curdate() group by `call`";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th><a href=index.php?today>呼号</a></th><th><a href=index.php?today&c>数量</a></th><th>地图</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo "<a href=index.php?call=$r[0]>$r[0]</a>";
        	echo "</td><td align=right>";
        	echo $r[1];
        	echo "</td><td>";
		echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$r[0]."&timerange=43200&tail=43200\" target=_blank>aprs.fi</a>";
        	echo "</td></tr>\n";
	}

	echo "</table>";
	exit(0);
}

if ($cmd=="call") {
	echo "今天收到的 $call APRS数据包 ";
	echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$call."&timerange=43200&tail=43200\" target=_blank>aprs.fi地图</a><p>";
	$q="select tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw from aprspacket where tm>curdate() and `call`=? order by tm desc";
	$stmt=$mysqli->prepare($q);
        $stmt->bind_param("s",$call);
        $stmt->execute();
	$meta = $stmt->result_metadata();

	$i=0;
	while ($field = $meta->fetch_field()) {
        	$params[] = &$r[$i];
        	$i++;
	}

	call_user_func_array(array($stmt, 'bind_result'), $params);
	echo "<table border=1 cellspacing=0><tr><th>时间</th><th>msg</th><th>raw packet</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[0];  //tm
        	echo "</td><td>";
		echo $r[2];  //datatype
		echo $r[3];  //lat
		echo $r[5];  //table 
		echo $r[4];  //lon
		echo $r[6];  //symbol
		echo $r[7];  //msg
        	echo "</td><td>";
		echo $r[8];  //msg
        	echo "</td></tr>\n";
	}
	echo "</table>";
	exit(0);
}

if ($cmd=="stats") {
?>

<script type="text/javascript" src="stats/swfobject.js"></script>

<div id="flashcontent1"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/one_day.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent1");
</script>
<p>
<div id="flashcontent2"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/30_day.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent2");
</script>

<?php
	exit(0);
}

if ($cmd=="about") {

include "about.html";

}
?>
