<?php

include "../db.php";

date_default_timezone_set( 'Asia/Shanghai');

if (!isset($_SESSION["jiupian"]))
	$_SESSION["jiupian"]=1;

if (!isset($_SESSION["span"]))
	$_SESSION["span"]=1;
$span = $_SESSION["span"];
if ( ($span<=0) || ($span >10) ) $span=1;

$startdate=time() - $span * 3600 * 24;

// 1 hour history
$startdate=time() - 3600;
$startdatestr=date("Y-m-d H:i:s",$startdate);

$title = file_get_contents( "title.txt" );
if( $title === FALSE )
        $title = "中国救援指挥平台";

if (isset($_REQUEST["tm"])) {
	$cmd="tm";
	$tm=$_REQUEST["tm"];
} else {
	$cmd="map";
}

$jiupian = 0; 	// 0 不处理
		// 1 存储的是地球坐标，转换成baidu显示, 默认情况
		// 2 存储的是火星坐标，转换成baidu显示

if ( ($cmd=="map") || ($cmd=="tm")) {
	$jiupian = 1;
	if (isset($_SESSION["jiupian"]))
		$jiupian=$_SESSION["jiupian"];
}

if($jiupian>0) {
	require "../wgtochina_baidu.php";
	$mp=new Converter();
}

function urlmessage($call,$icon, $dtmstr, $msg, $ddt, $lon, $lat) {
	$m = "<font face=微软雅黑 size=2><img src=".$icon."> ".$call;
	$m = $m."<hr color=green>".$dtmstr."<br>";
	$m = $m."<b>".substr($lat,0,2)."°".substr($lat,2,5)."'".substr($lat,7,1)." ";
	$m = $m."<b>".substr($lon,0,3)."°".substr($lon,3,5)."'".substr($lon,8,1);
	$m = $m."</b><br>";
	if (  (strlen($msg)>=32) &&
		(substr($msg,3,1)=='/') &&
		(substr($msg,7,1)=='g') &&
		(substr($msg,11,1)=='t') &&
		(substr($msg,15,1)=='r') )  // 090/001g003t064r000p000h24b10212 or 000/000g000t064r000P000p000h39b10165
	{
		$c = substr($msg,0,3)*1; //wind dir
		$s = number_format(substr($msg,4,3)*0.447,1); //wind speed
		$g = number_format(substr($msg,8,3)*0.447,1); //5min wind speed
		$t = number_format((substr($msg,12,3)-32)/1.8,1); //temp
		$r = number_format(substr($msg,16,3)*25.4/100,1); //rainfall in mm 1 hour
		$msg = strstr($msg,"p");
		$p = number_format(substr($msg,1,3)*25.4/100,1); //rainfall in mm 24 hour
		$msg = strstr($msg,"h");
		$h = substr($msg,1,2);	//hum
		$b = substr($msg,4,5)/10; //press
		$msg = substr($msg,9);
		$m = $m."<b>温度".$t."°C 湿度".$h."% 气压".$b."mpar<br>";
		$m = $m."风".$c."°".$s."m/s(大风".$g."m/s)<br>";
	 	$m = $m."雨".$r."mm/1h ".$p."mm/24h</b><br>";
	}
	if (  (strlen($msg)>=27) &&
		(substr($msg,3,1)=='/') &&
		(substr($msg,7,1)=='g') &&
		(substr($msg,11,1)=='t') &&
		(substr($msg,15,1)=='P') )  // 090/000g002t046P099h51b10265V130OTW1
	{
		$c = substr($msg,0,3)*1; //wind dir
		$s = number_format(substr($msg,4,3)*0.447,1); //wind speed
		$g = number_format(substr($msg,8,3)*0.447,1); //5min wind speed
		$t = number_format((substr($msg,12,3)-32)/1.8,1); //temp
		$r = number_format(substr($msg,16,3)*25.4/100,1); //rainfall in mm 1 hour
		$msg = strstr($msg,"h");
		$h = substr($msg,1,2);	//hum
		$b = substr($msg,4,5)/10; //press
		$msg = substr($msg,9);
		$m = $m."<b>温度".$t."°C 湿度".$h."% 气压".$b."mpar<br>";
		$m = $m."风".$c."°".$s."m/s(大风".$g."m/s)<br>";
	 	$m = $m."雨".$r."mm/自午夜起</b><br>";
	}
	if( (strlen($msg)>=7) &&
		(substr($msg,3,1)=='/'))  // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=number_format(substr($msg,4,3)*1.852,1);
		$m = $m."<b>".$speed." km/h ".$dir."°";
		$msg = substr($msg,7);
		if( substr($msg,0,3)=='/A=') {      // 178/061/A=000033
			if(strstr($msg, "51Track X2A")) {
				$alt=number_format(substr($msg,3,6),1);
			} else {
				$alt=number_format(substr($msg,3,6)*0.3048,1);
			}
			$m=$m." 海拔".$alt."m</b><br>";
			$msg = substr($msg,9);
		} else $m=$m."</b><br>";
	} else if( (strlen($msg)>=9) &&
		(substr($msg,0,3)=='/A=') )      // /A=000033
	{
		$alt=number_format(substr($msg,3,6)*0.3048,1);
		$m = $m."<b> 海拔".$alt."m</b><br>";
		$msg = substr($msg,9);
	} else if( ($ddt=='`')  &&
		 (strlen($msg)>=9) )   // `  0+jlT)v/]"4(}=
	{
		$speed = (ord(substr($msg,3,1))-28)*10;
		$t=ord(substr($msg,4,1))-28;
		$speed = $speed + $t/10;
		if($speed>=800) $speed-=800;
		$speed = number_format($speed*1.852,1);
		$dir = ($t%10)*100 + ord(substr($msg,5,1))-28;
		if($dir>=400) $dir -= 400;
		$msg = substr($msg,8);
		$alt=0;
		
		if((substr($msg,0,1)==']') || (substr($msg,0,1)=='`') )
			$msg=substr($msg,1);
		if( (strlen($msg)>=4) && (substr($msg,3,1)=='}') ) {
			$alt = (ord( substr($msg,0,1)) -33)*91*91+
				(ord( substr($msg,1,1)) -33)*91 +
				(ord( substr($msg,2,1)) -33) -10000;
			$alt = number_format($alt,1);
			$msg = substr($msg,4);
		}
		$m = $m."<b>".$speed." km/h ".$dir."° 海拔".$alt."m</b><br>";
	}  
	if( (strlen($msg)>=7) &&
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
		
	$msg=rtrim($msg);
		
	$m = $m."</font><font color=green face=微软雅黑 size=2>".addcslashes(htmlspecialchars($msg),"\\\r\n'\"")."</font>";
	return $m;	
}

function strtolat($glat) {
	$lat = 0;
	$lat = substr($glat,0,2) + substr($glat,2,5)/60;
	if(substr($glat,7,1)=='S')
		$lat = -$lat;
	return $lat;
}
function strtolon($glon) {
	$lon = 0;
	$lon = substr($glon,0,3) + substr($glon,3,5)/60;
	if(substr($glon,8,1)=='W')
		$lon = -$lon;
	return $lon;
}
if ($cmd=="tm") {
	global $startdatestr;
	$starttm = microtime(true);
//删除10天前的每个台站最后状态数据包
	$q="delete from lastpacket where tm<=date_sub(now(),INTERVAL 10 day)";
	$mysqli->query($q);
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";

	$q="select lat,lon,`call`,unix_timestamp(tm),tm,concat(`table`,symbol),msg,datatype from aprspacket where tm>=FROM_UNIXTIME(?) and tm>=? and lat<>'' and not lat like '0000.00%'";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("is",$tm,$startdatestr);
        $stmt->execute();
       	$stmt->bind_result($glat,$glon,$dcall,$dtm,$dtmstr,$dts,$dmsg,$ddt);

	while($stmt->fetch()) {
                $lat = strtolat($glat);
                $lon = strtolon($glon);
		if($jiupian==1) {
			$p=$mp->WGStoBaiDuPoint($lon,$lat);
			$lon = $p->getX();
			$lat = $p->getY();
		} else if($jiupian==2) {
			$p=$mp->ChinatoBaiDuPoint($lon,$lat);
			$lon = $p->getX();
			$lat = $p->getY();
		} 
		$icon = "/img/".bin2hex($dts).".png";
		$dmsg = urlmessage($dcall, $icon, $dtmstr, $dmsg, $ddt, $glon, $glat);
		echo "setstation(".$lon.",".$lat.",\"".$dcall."\",".$dtm.",\"".$icon."\",\n\"".$dmsg."\");\n";
	}
	$stmt->close();
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";

	$q="select count(*) from lastpacket where tm>=\"".$startdatestr."\"";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "updatecalls(".$r[0].");\n";
	$q="select count(*) from aprspacket where tm>=\"".$startdatestr."\"";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	$r[0]=intval($r[0]);
	echo "updatepkts(".$r[0].");\n";
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";
	if($tm==0) {
		if(isset($lon)) 
			echo "map.centerAndZoom(new BMap.Point($lon,$lat),12);\n";
	}
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
	</style>
	<title><?php echo $title; ?></title>
	<script type="text/javascript" src="http://api.map.baidu.com/api?v=2.0&ak=7RuEGPr12yqyg11XVR9Uz7NI"></script>
</head>
<body>
<div id="allmap"></div>
</body>
</html>
<script type="text/javascript">
var markers = {};
var lasttms = {};
var iconurls = {};
var infowindows = {};
var lasttm=0;
var movepaths = {};
var polylines = {};

var colors = ["#1400FF","#14F0FF","#78FF00","#FF78F0","#0078F0","#F0FF14","#FF78F0","#FF78F0","#FF78F0"];
var colorindex = 0;

function getcolor(){
	var c = colors[colorindex];
	colorindex++;
	if(colorindex==colors.length) 
		colorindex =0;
	return c;
}
function updatecalls(calls) {
	console.log("calls:"+calls);
}
function updatepkts(pkts) {
	console.log("pkts:"+pkts);
}

function addp(lon,lat,msg) {
	var icon = new BMap.Icon("/p.png", new BMap.Size(3, 3));	
	var m = new BMap.Marker(new BMap.Point(lon,lat), {icon: icon});
	var infowindow = new BMap.InfoWindow(msg, {width:300});
	(function(){
        	m.addEventListener('click', function(){
            	this.openInfoWindow(infowindow);
        	});
	})();
	map.addOverlay(m);
}

function setstation(lon, lat, label, tm, iconurl, msg)
{	
	if(markers.hasOwnProperty(label)) {   // call已经存在
		if(tm<=lasttms[label]) return; // 同一时间的点已经更新过了（假定每秒最多传回来一个点，第二个点不再处理）
		markers[label].setPosition( new BMap.Point(lon, lat) ); // 更改最后的点位置
		infowindows[label].setContent(msg);
		if(iconurls[label]!=iconurl) {
			var nicon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 12)});
			markers[label].setIcon(nicon);
			iconurls[label]=iconurl;
		}
		lasttms[label] = tm;
		if(tm>lasttm) lasttm = tm;
		// 更新航迹
		var p = new BMap.Point(lon,lat);
		movepaths[label].push (p);
		polylines[label].setPath(movepaths[label]);
		addp(lon,lat,msg);
		return;
	}
	// 新call
	var icon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 12)});	
	var marker = new BMap.Marker(new BMap.Point(lon,lat), {icon: icon});
	var lb = new BMap.Label(label, {offset: new BMap.Size(20,-10)});
	lb.setStyle({border:0, background: "#eeeeee"});
	marker.setLabel(lb);
	var infowindow = new BMap.InfoWindow(msg, {width:300});
	(function(){
        	marker.addEventListener('click', function(){
            	this.openInfoWindow(infowindow);
        	});
	})();
	map.addOverlay(marker);
	markers[label]= marker;
	lasttms[label] = tm;
	iconurls[label]=iconurl;
	infowindows[label]=infowindow;
	if(tm>lasttm) lasttm = tm;
	// 处理航迹
	var p = new BMap.Point(lon,lat);
	movepaths[label] = new Array();
	movepaths[label].push (p);
	polylines[label] = new Array();
	polylines[label] = new BMap.Polyline(movepaths[label],{strokeColor:getcolor(), strokeWeight:4, strokeOpacity:0.9});
	map.addOverlay(polylines[label]);
	addp(lon,lat,msg);
}

var xmlHttpRequest;     //XmlHttpRequest对象     
function createXmlHttpRequest(){     
	var http_request = false;
	if (window.XMLHttpRequest) { // Mozilla, Safari,...
		http_request = new XMLHttpRequest();
		if (http_request.overrideMimeType) {
			http_request.overrideMimeType('text/xml');
		}
        } else if (window.ActiveXObject) { // IE
		try {
			http_request = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				http_request = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {}
		}
        }
        if (!http_request) {
		alert('Giving up :( Cannot create an XMLHTTP instance');
		return false;
        }
	return http_request;
}     

function UpdateStation(){     
//	alert(lastupdatetm);
	var b = map.getBounds();
        var url = window.location.protocol+"//"+window.location.host+":"+window.location.port+"/"+window.location.pathname+"?tm="+lasttm;
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
        if(xmlHttpRequest.readyState == 4){
		if(xmlHttpRequest.status == 200){  
        		var b = xmlHttpRequest.responseText;  
			eval(b);
		}
       	   	setTimeout("UpdateStation();","2000");  
        }     
}    

function centertocurrent(){
	var geolocation = new BMap.Geolocation();
	geolocation.getCurrentPosition(function(r){
		if(this.getStatus() == BMAP_STATUS_SUCCESS){
			map.centerAndZoom(r.point,12);
	}},{enableHighAccuracy: false});
}

// 百度地图API功能
var map = new BMap.Map("allmap");
map.enableScrollWheelZoom();

var top_left_control = new BMap.ScaleControl({anchor: BMAP_ANCHOR_TOP_LEFT});// 左上角，添加比例尺
var top_left_navigation = new BMap.NavigationControl();  //左上角，添加默认缩放平移控件
	
//添加控件和比例尺
map.addControl(top_left_control);        
map.addControl(top_left_navigation);     
map.addControl(new BMap.MapTypeControl());
map.centerAndZoom(new BMap.Point(108.940178,34.5), 6);

//centertocurrent();

//createXmlHttpRequest();  
UpdateStation();  
</script>
<?php
	exit(0);
}

?>
