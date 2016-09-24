<?php

$aprs_server = "127.0.0.1";
$msg="";

function sendaprs($call, $lat, $lon, $desc, $ts)
{	global $aprs_server;
	global $msg;
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_connect($s, $aprs_server, 14580 );
	$N = 'N';
	if($lat < 0) {
		$lat = - $lat;
		$N = 'S';
	}
	$E = 'E';
	if($lon < 0) {
		$lon = -$lon;
		$E = 'W';
	}	
	$msg = $call.">WEB2NT:!";
	$msg = $msg.sprintf("%02d%05.2f%s%s", floor($lat), ($lat-floor($lat))*60, $N, substr($ts,0,1));
	$msg = $msg.sprintf("%03d%05.2f%s%s", floor($lon), ($lon-floor($lon))*60, $E, substr($ts,1,1));
	$msg = $msg.sprintf("%s%s", $desc, "\r\n");
//	echo $msg;
//	echo "<p>";
	socket_send ($s, $msg, strlen($msg), 0);
	$msg = date("Y-m-d H:i:s ").$msg;
}

$call =@$_REQUEST["call"];
$lat =@$_REQUEST["lat"];
$lon =@$_REQUEST["lon"];
$ts =@$_REQUEST["ts"];
$desc =@$_REQUEST["desc"];

if($ts=="") $ts="/$";

if($call=="") {
	$call = $_COOKIE["call"];
	$ts = $_COOKIE["ts"];
	$desc = $_COOKIE["desc"];
	if($ts=="") $ts="/$";
	$call = strtoupper($call);
}else {
	$lat =@$_REQUEST["lat"];
	$lon =@$_REQUEST["lon"];
	$ts =@$_REQUEST["ts"];
	$desc =@$_REQUEST["desc"];
	$call = strtoupper($call);
	setcookie("call",$call,time()+24*3600*365);
	setcookie("ts",$ts,time()+24*3600*365);
	setcookie("desc",$desc,time()+24*3600*365);

	$lati = explode(".",$lat);
	if(count($lati)<=2) 
		$latui = $lat;
	else if(strlen($lati[2])==3)
		$latui = $lati[0] + ($lati[1]+$lati[2]/1000)/60;
	else
		$latui = $lati[0] + $lati[1]/60+$lati[2]/3600;

	$loni = explode(".",$lon);
	if(count($loni)<=2) 
		$lonui = $lon;
	else if(strlen($loni[2])==3)
		$lonui = $loni[0] + ($loni[1]+$loni[2]/1000)/60;
	else
		$lonui = $loni[0] + $loni[1]/60 +$loni[2]/3600;
}
?>

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="initial-scale=1.0" />
</head>
<body>
<?php

if(@$_REQUEST["send"]==1) 
	sendaprs($call,$latui,$lonui,$desc,$ts);

echo "<h3>发送APRS位置信息</h3>\n";
echo "<form name=aprs action=index.php method=post>";
echo "<input type=hidden name=send value=1>";
echo "<table>";
echo "<tr><td>呼号：</td><td><input name=call value=\"".$call."\"></td></tr>\n";
echo "<tr><td>经度：</td><td><input id=lon name=lon value=\"".$lon."\"></td></tr>\n";
echo "<tr><td>纬度：</td><td><input id=lat name=lat value=\"".$lat."\"></td></tr>\n";
echo "<tr><td>类型：</td><td><input name=ts value=\"".$ts."\"></td></tr>\n";
echo "<tr><td>消息：</td><td><input name=desc value=\"".$desc."\"></td></tr>\n";
echo "<tr><td colspan=2 align=center><br><button type=button onClick=\"get_location();\">当前位置</button>&nbsp;&nbsp;&nbsp;";
echo "<input type=submit value=\"发送信息\"></input></td></tr>\n";
echo "</table>";
echo "</from><p>";
echo "经纬度格式（依据小数点数或数字位数）<br>\n";
echo "<table>";
echo "<tr><td>ddd.dddddd</td><td>度.度</td><td>31.12035º</td></tr>";
echo "<tr><td>ddd.mm.ss</td><td>度.分.秒</td><td>31º12'42\"</td></tr>";
echo "<tr><td>ddd.mm.mmm</td><td>度.分.分（3位）</td><td>31º10.335'</td></tr>";
echo "</table>";
?>

<div id=out><?php if($msg!="") echo "<font color=green>".$msg."</font>"; ?></div>

<script type="text/javascript">
function get_location(){
	var output = document.getElementById("out");

	if (!navigator.geolocation){
		output.innerHTML = "<p>您的浏览器不支持地理位置</p>";
		return;
	}

	function success(position) {
		var latitude  = position.coords.latitude;
		var longitude = position.coords.longitude;

		output.innerHTML = '<p>获取位置成功</p>';
		document.getElementById("lon").value = longitude;
		document.getElementById("lat").value = latitude;
  	};

  	function error(err) {
    		output.innerHTML = "无法获取位置 Error:" + err.code + ': ' + err.message;
  	};

  	output.innerHTML = "<p>Locating…</p>";

	var options = {
  		enableHighAccuracy: true,
  		timeout: 5000,
  		maximumAge: 0
	};
  	navigator.geolocation.getCurrentPosition(success, error, options);
}
</script>
