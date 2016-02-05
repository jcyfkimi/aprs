<?php

include "db.php";

if (isset($_REQUEST["new"])) {
	$cmd="new";
	header("refresh: 3;");
} else if (isset($_REQUEST["today"])) {
	$cmd="today";
	header("refresh: 60;");
} else if (isset($_REQUEST["map"])) {
	$cmd="map";
	$call=$_REQUEST["call"];
	if (isset($_REQUEST["jiupian"]))
		$jiupian=1;
	else $jiupian=0;
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

function disp_map($call) {
	echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$call."&timerange=43200&tail=43200\" target=_blank>aprs.fi</a> ";
	echo "<a href=\"index.php?map&jiupian&call=".$call."\" target=_blank>baidu</a> ";
}

if ($cmd!="map") {
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
}
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
		echo iconv("utf-8","gb2312",$r[2]);  //raw
        	echo "</td><td>";
		disp_map($r[1]);
        	echo "</td></tr>\n";
	}
	echo "</table>\n";


	echo "<h3>最新收到的无法解析APRS数据包</h3>";
	$q="select tm,`call`,raw from aprspacket where tm>=curdate() and lat='' order by tm desc limit 10";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th>时间</th><th>呼号</th><th>APRS Packet</th><th>地图</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo $r[0];
        	echo "</td><td>";
        	echo "<a href=index.php?call=$r[1]>$r[1]</a>";
        	echo "</td><td>";
		echo iconv("utf-8","gb2312",$r[2]);  //raw
        	echo "</td><td>";
		disp_map($r[1]);
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
		disp_map($r[0]);
        	echo "</td></tr>\n";
	}

	echo "</table>";
	exit(0);
}

if ($cmd=="call") {
	echo "今天收到的 $call APRS数据包 ";
	disp_map($call);
	echo "<p>";
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
		echo iconv("utf-8","gb2312",$r[7]);  //msg
        	echo "</td><td>";
		echo iconv("utf-8","gb2312",$r[8]);  //raw
        	echo "</td></tr>\n";
	}
	echo "</table>";
	exit(0);
}

if ($cmd=="stats") {
?>

<script type="text/javascript" src="stats/swfobject.js"></script>

<table>
<tr>
<td>
<div id="flashcontent1"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/48_hour_pkt.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent1");
</script>
</td>
<td>
<div id="flashcontent2"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/48_hour_call.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent2");
</script>
</td>
</tr>
<tr>
<td>
<div id="flashcontent3"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/30_day_pkt.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent3");
</script>
</td>
<td>
<div id="flashcontent4"></div>
<script type="text/javascript">
var so = new SWFObject("stats/open-flash-chart.swf", "chart", "700", "350", "9", "#FFFFFF");
so.addVariable("data", "stats/30_day_call.php");
so.addParam("allowScriptAccess", "sameDomain");
so.write("flashcontent4");
</script>
</td>
</tr>
</table>
<?php
	exit(0);
}

if ($cmd=="map") {
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<style type="text/css">
		body, html{width: 100%;height: 100%;margin:0;font-family:"微软雅黑";}
		#allmap {height:100%; width: 100%;}
		#control{width:100%;}
	</style>
	<script type="text/javascript" src="http://api.map.baidu.com/api?v=2.0&ak=7RuEGPr12yqyg11XVR9Uz7NI"></script>
</head>
<body>
	<div id="allmap"></div>
</body>
</html>
<script type="text/javascript">
	// 百度地图API功能
	var map = new BMap.Map("allmap");
	map.enableScrollWheelZoom();

	var top_left_control = new BMap.ScaleControl({anchor: BMAP_ANCHOR_TOP_LEFT});// 左上角，添加比例尺
	var top_left_navigation = new BMap.NavigationControl();  //左上角，添加默认缩放平移控件
	var top_right_navigation = new BMap.NavigationControl({anchor: BMAP_ANCHOR_TOP_RIGHT, type: BMAP_NAVIGATION_CONTROL_SMALL}); //右上角，仅包含平移和缩放按钮
	/*缩放控件type有四种类型:
	BMAP_NAVIGATION_CONTROL_SMALL：仅包含平移和缩放按钮；BMAP_NAVIGATION_CONTROL_PAN:仅包含平移按钮；BMAP_NAVIGATION_CONTROL_ZOOM：仅包含缩放按钮*/
	
	//添加控件和比例尺
	map.addControl(top_left_control);        
	map.addControl(top_left_navigation);     
	map.addControl(top_right_navigation);    

	var polyline = new BMap.Polyline([
<?php
//		new BMap.Point(116.399, 39.910),
//		new BMap.Point(116.405, 39.920),
//		new BMap.Point(116.423493, 39.907445)
	
	$q="select lat,lon from aprspacket where tm>curdate() and `call`=? order by tm";
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
	if($jiupian) {
		require "wgtochina_lb.php";
			$mp=new Converter();
		$x_pi = 3.14159265358979324 * 3000.0 / 180.0; 
	}
	while($stmt->fetch()) {
		$lat = substr($r[0],0,2) + substr($r[0],2,5)/60;
		$lon = substr($r[1],0,3) + substr($r[1],3,5)/60;
		if($jiupian) {
			$p=$mp->getEncryPoint($lon,$lat);
			$lon = $p->getX();
			$lat = $p->getY();
			// gg-> baidu
			$x = $lon;
			$y = $lat;  
    			$z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);  
    			$theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);  
    			$bd_lon = $z * cos($theta) + 0.0065;  
    			$bd_lat = $z * sin($theta) + 0.006;
			$lon = $bd_lon;
			$lat = $bd_lat;
		} 
		echo "new BMap.Point(".$lon.",".$lat."),\n";
	}
?>
	], {strokeColor:"blue", strokeWeight:2, strokeOpacity:0.5});   //创建折线
	map.addOverlay(polyline);   //增加折线
<?php
	echo "map.centerAndZoom(new BMap.Point(".$lon.",".$lat."), 15);\n";
	echo "var marker= new BMap.Marker(new BMap.Point(".$lon.",".$lat."));\n";
	echo "marker.setLabel(new BMap.Label(\"".$call."\",{offset: new BMap.Size(20,-10)}));\n";
	echo "map.addOverlay(marker);\n";
?>
</script>


<?php
}
if ($cmd=="about") {
include "about.html";
}
?>
