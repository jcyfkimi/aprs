<?php

include "db.php";

if (isset($_REQUEST["tm"])) {
	$cmd="tm";
	$tm=$_REQUEST["tm"];
	if (isset($_REQUEST["nojiupian"]))
		$jiupian=0;
	else $jiupian=1;
}else if (isset($_REQUEST["new"])) {
	$cmd="new";
	header("refresh: 5;");
} else if (isset($_REQUEST["today"])) {
	$cmd="today";
	header("refresh: 60;");
} else if (isset($_REQUEST["map"])) {
	$cmd="map";
	$call=@$_REQUEST["call"];
	if (isset($_REQUEST["nojiupian"]))
		$jiupian=0;
	else $jiupian=1;
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
	$cmd="map";
	if (isset($_REQUEST["nojiupian"]))
		$jiupian=0;
	else $jiupian=1;
}

if(@$jiupian) {
	require "wgtochina_lb.php";
	$mp=new Converter();
}

function urlmessage($call,$icon, $dtmstr, $msg) {
	$m = "<img src=".$icon."> ".$call." <a href=".$_SERVER["PHP_SELF"]."?call=".$call." target=_blank>数据包</a> <a id=\\\"m\\\" href=\\\"#\\\" onclick=\\\"javascript:monitor_station('".$call."');return false;\\\">跟踪台站</a> <hr color=green>".$dtmstr."<br>";
	if( (strlen($msg)>=16) &&
		(substr($msg,3,1)=='/') &&
		(substr($msg,7,3)=='/A=') )      // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=substr($msg,4,3)*.16;
		$alt=substr($msg,10,6)*0.3;
		$m = $m."<b>".$speed." km/h ".$dir."° 海拔".$alt."m</b><br>";
		$msg = substr($msg,16);
	} else if( (strlen($msg)>=9) &&
		(substr($msg,0,3)=='/A=') )      // /A=000033
	{
		$alt=substr($msg,3,6)*0.3;
		$m = $m."<b> 海拔".$alt."m</b><br>";
		$msg = substr($msg,9);
	} else if( (strlen($msg)>=7) &&
		(substr($msg,0,1)=='`') &&
		(substr($msg,4,1)=='}') )    // `"4/}_"
	{
		$alt = (ord( substr($msg,1,1)) -33)*91*91+
			(ord( substr($msg,2,1)) -33)*91 +
			(ord( substr($msg,3,1)) -33) -10000;
		$m = $m."<b> 海拔".$alt."m</b><br>";
		$msg = substr($msg,6);
	} else if( (strlen($msg)>=7) &&
                (substr($msg,0,3)=='PHG') )  // PHG
        {
		$pwr = ord(substr($msg,3,1))-ord('0');
		$pwr = $pwr*$pwr;
		$h = ord(substr($msg,4,1))-ord('0');
		$h = pow(2,$h)*10*0.3048;
		$h = round($h);
		$g = substr($msg,5,1);
                $m = $m."<b>功率".$pwr."瓦 天线高度".$h."m 增益".$g."dB</b><br>";
                $msg = substr($msg,7);
	}
		
	$msg=iconv("utf-8","gb2312",$msg); 
	$msg=rtrim($msg);
		
	$m = $m.htmlspecialchars($msg);
	//$m = $m."<br><font color=green>".urlencode($msg)."</font>";
	return $m;	
}
if ($cmd=="tm") {
	$q=" from lastpacket where tm<=curdate()";
	$mysqli->query($q);
	$q="select lat,lon,`call`,unix_timestamp(tm),tm,concat(`table`,symbol),msg from lastpacket where tm>=FROM_UNIXTIME(?) and lat<>'' and not lat like '0000.00%'";
	$stmt=$mysqli->prepare($q);
        $stmt->bind_param("i",$tm);
        $stmt->execute();
       	$stmt->bind_result($glat,$glon,$dcall,$dtm,$dtmstr,$dts,$dmsg);

	while($stmt->fetch()) {
                $lat = substr($glat,0,2) + substr($glat,2,5)/60;
                $lon = substr($glon,0,3) + substr($glon,3,5)/60;
		if($jiupian) {
			$p=$mp->getBDEncryPoint($lon,$lat);
			$lon = $p->getX();
			$lat = $p->getY();
		}
		$icon = "img/".bin2hex($dts).".png";
		$dmsg = urlmessage($dcall, $icon, $dtmstr, $dmsg);
		echo "setstation(".$lon.",".$lat.",\"".$dcall."\",".$dtm.",\"".$icon."\",\n\"".$dmsg."\");\n";
	}
	$stmt->close();

	$q="select count(distinct(`call`)) from aprspacket where tm>=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "document.getElementById(\"calls\").innerHTML = ".$r[0].";\n";
	$q="select count(*) from aprspacket where tm>=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "document.getElementById(\"pkts\").innerHTML = ".$r[0].";\n";


	if (!isset($_REQUEST["call"])) 
		exit(0);
	$call=$_REQUEST["call"];
	if($call=="") exit(0);
	$pathlen = @$_REQUEST["pathlen"];
	if($pathlen=="") $pathlen=0;
	$q="select lat,lon from aprspacket where tm>curdate() and `call`=? and lat<>'' and not lat like '0000.00%' order by tm limit 50000 offset ?";
        $stmt=$mysqli->prepare($q);
        $stmt->bind_param("si",$call,$pathlen);
        $stmt->execute();
        $stmt->bind_result($glat, $glon);

	$pathmore=0;
	echo "if(movepath.lenght>0) map.removeOverlay(polyline);\n";
        while($stmt->fetch()) {
                $lat = substr($glat,0,2) + substr($glat,2,5)/60;
                $lon = substr($glon,0,3) + substr($glon,3,5)/60;
                if($jiupian) {
                        $p=$mp->getBDEncryPoint($lon,$lat);
                        $lon = $p->getX();
                        $lat = $p->getY();
                }
		$pathmore=1;
                echo "addpathpoint(".$lon.",".$lat.");\n";
        }	
	if($pathmore==1) {
		echo "map.panTo(new BMap.Point(".$lon.",".$lat."));\n";
		echo "polyline = new BMap.Polyline(movepath,{strokeColor:\"blue\", strokeWeight:3, strokeOpacity:0.5});\n";
		echo "map.addOverlay(polyline);\n";
	}
	exit(0);
}

function disp_map($call) {
	echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$call."&timerange=43200&tail=43200\" target=_blank>aprs.fi</a> ";
	echo "<a href=\"http://aprs.hamclub.net/mtracker/map/aprs/".$call."\" target=_blank>hamclub</a> ";
	echo "<a href=\"".$_SERVER["PHP_SELF"]."?map&call=".$call."\" target=_blank>baidu</a> ";
}

function top_menu() {
	global $mysqli, $cmd;
	$blank="";
	if($cmd=="map") $blank = " target=_blank";
	echo "<a href=".$_SERVER["PHP_SELF"]."?new".$blank.">最新数据包</a> <a href=".$_SERVER["PHP_SELF"]."?today".$blank.
	">今天数据包</a> <a href=".$_SERVER["PHP_SELF"]."?stats".$blank.">数据包统计</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?map target=_blank>数据包实况</a> <div id=calls>";
	$q="select count(distinct(`call`)) from aprspacket where tm>=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo $r[0]."</div> 呼号发送了 <div id=pkts>";
	$q="select count(*) from aprspacket where tm>=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo $r[0]."</div> 数据包 ";

	echo " <a href=".$_SERVER["PHP_SELF"]."?about target=_blank>关于本站</a> <div id=msg></div><p>";
}

if ( ($cmd!="map") ) {
?>

<html><head><meta http-equiv="Content-Type" content="text/html; charset=gb2312" />
	<title>APRS relay server</title>
</head>
<style type="text/css">
<!--
div{ display:inline} 
-->
</style>

<body bgcolor=#dddddd>

<?php

top_menu();
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
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[1]>$r[1]</a>";
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
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[1]>$r[1]</a>";
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
		$q = "select `call`, count(*) from aprspacket where tm>curdate() group by substr(`call`,3)";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th><a href=".$_SERVER["PHP_SELF"]."?today>呼号</a></th><th><a href=".$_SERVER["PHP_SELF"]."?today&c>数量</a></th><th>地图</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[0]>$r[0]</a>";
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
		#full {height:100%; width: 100%;}
		#top {height:25px; width: 100%;}
		#allmap {height:100%; width: 100%;}
		#control{width:100%;}
		#calls { display:inline} 
		#pkts { display:inline} 
		#msg { display:inline} 
	</style>
	<title>APRS数据包实况</title>
	<script type="text/javascript" src="http://api.map.baidu.com/api?v=2.0&ak=7RuEGPr12yqyg11XVR9Uz7NI"></script>
</head>
<body>
<div id="full">
	<div id="top"><?php top_menu();?></div>
	<div id="allmap"></div>
</div>
</body>
</html>
<script type="text/javascript">

var markers = new Array();
var labels = new Array();
var lasttms = new Array();
var iconurls = new Array();
var totalmarkers=0;
var lastupdatetm=0;
var movepath = new Array();
var call="";

function monitor_station(mycall) {
//	alert(mycall);
	if(call==mycall) return;
	if(movepath.length>0) {
		map.removeOverlay(polyline);
		movepath.splice(0,movepath.length);
	}	
	call=mycall;
	document.getElementById("msg").innerHTML = "正在跟踪"+call;
       	map.setZoom(15);
}

var xmlHttpRequest;     
//XmlHttpRequest对象     
function createXmlHttpRequest(){     
        if(window.ActiveXObject){ //如果是IE  
            return new ActiveXObject("Microsoft.XMLHTTP");     
        }else if(window.XMLHttpRequest){ //非IE浏览器     
            return new XMLHttpRequest();     
        }     
}     

function UpdateStation(){     
//	alert(lastupdatetm);
        var url = '<?php echo "http://".$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"]."?tm=";?>'+lastupdatetm+"&call="+call+"&pathlen="+movepath.length;  
        //1.创建XMLHttpRequest组建     
        xmlHttpRequest = createXmlHttpRequest();     
             
        //2.设置回调函数     
        xmlHttpRequest.onreadystatechange = UpdateStationDisplay;
             
        //3.初始化XMLHttpRequest组建     
        xmlHttpRequest.open("post",url,true);     
             
        //4.发送请求     
        xmlHttpRequest.send(null);       
}          
    //回调函数     
function UpdateStationDisplay(){     
        if(xmlHttpRequest.readyState == 4 && xmlHttpRequest.status == 200){  
        	var b = xmlHttpRequest.responseText;  
		eval(b);
       	   	setTimeout("UpdateStation();","2000");  
            	return b;  
        }     
}    

function addpathpoint(lon, lat){
	var p = new BMap.Point(lon,lat);
	movepath.push (p);
	
}


function addstation(lon, lat, label, tm, iconurl, msg)
{
	var icon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 24)});	
	var marker = new BMap.Marker(new BMap.Point(lon,lat), {icon: icon  });
	marker.setLabel(new BMap.Label(label, {offset: new BMap.Size(20,-10)}));
	(function(){
        	marker.addEventListener('click', function(){
            	this.openInfoWindow(new BMap.InfoWindow(msg));
        	});
	})();
	map.addOverlay(marker);
	markers[totalmarkers]= marker;
	labels[totalmarkers] = label;
	lasttms[totalmarkers] = tm;
	iconurls[totalmarkers]=iconurl;
	if(tm>lastupdatetm) lastupdatetm = tm;
	totalmarkers=totalmarkers+1;
	return marker;
}

function setstation(lon, lat, label,tm, iconurl, msg)
{	for (var i=0; i< totalmarkers; i++) {
		if(labels[i]==label) {
			if(tm<=lasttms[i]) return;
			markers[i].setPosition( new BMap.Point(lon, lat) );
			(function(){
        		markers[i].addEventListener('click', function(){
            			this.openInfoWindow(new BMap.InfoWindow(msg));
        			});    
    			})();
			lasttms[i] = tm;
			if(tm>lastupdatetm) lastupdatetm = tm;
			if(iconurls[i]!=iconurl) {
				var nicon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {  anchor: new BMap.Size(12, 24)});	
				markers[i].setIcon(nicon);
				iconurls[i]=iconurl;
			}
			markers[i].setZIndex(this.maxZindex++);
    			markers[i].setAnimation(BMAP_ANIMATION_BOUNCE);
			if(call==labels[i])  {  // 正在跟踪
				setTimeout(function(){ markers[i].setAnimation(null); }, 500);
			} else 
			setTimeout(function(){ markers[i].setAnimation(null); }, 500);
			return;
		}
	}
	var marker = addstation(lon, lat, label, tm, iconurl, msg);
	marker.setZIndex(this.maxZindex++);
    	marker.setAnimation(BMAP_ANIMATION_BOUNCE);
	setTimeout(function(){ marker.setAnimation(null); }, 500);
}

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

<?php
	echo "map.centerAndZoom(new BMap.Point(108.940178,34.5), 6);\n";
        $call=@$_REQUEST["call"];
        if($call!="")  {
		echo "monitor_station(\"$call\");\n";
	}
?>
createXmlHttpRequest();  
setTimeout("UpdateStation();","1000");  
</script>
<?php
	exit(0);
}

if ($cmd=="about") {
include "about.html";
}
?>
