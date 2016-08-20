<?php

include "db.php";

$ak = "7RuEGPr12yqyg11XVR9Uz7NI";

date_default_timezone_set( 'Asia/Shanghai');

if (!isset($_SESSION["jiupian"]))
	$_SESSION["jiupian"]=1;

if (!isset($_SESSION["span"]))
	$_SESSION["span"]=1;
$span = $_SESSION["span"];
if ( ($span<=0) || ($span >10) ) $span=1;
$span--;
$startdate=date_create();
date_sub($startdate,date_interval_create_from_date_string("$span days"));
$startdatestr=date_format($startdate,"Y-m-d 00:00:00");

$title = file_get_contents( "title.txt" );
if( $title === FALSE )
	$title = "APRS地图";

if (isset($_REQUEST["tm"])) {
	$cmd="tm";
	$tm=$_REQUEST["tm"];
} else if (isset($_REQUEST["map"])) {
	$cmd="map";
}else if (isset($_REQUEST["new"])) {
	$cmd="new";
	header("refresh: 5;");
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
} else if (isset($_REQUEST["track"])) {
	$cmd="track";
	$call=$_REQUEST["track"];
} else if (isset($_REQUEST["ge"])) {
	$cmd="ge";
} else if (isset($_REQUEST["about"])) {
	$cmd="about";
} else if (isset($_REQUEST["setup"])) {
	$cmd="setup";
} else {
	$cmd="map";
}

if ( $cmd=="map") {
	$call=@$_REQUEST["call"];
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
	require "wgtochina_baidu.php";
	$mp=new Converter();
}

function GetIP(){  
	$realip = '';  
	$unknown = 'unknown';  
	if (isset($_SERVER)){  
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)){  
			$arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);  
			foreach($arr as $ip){  
				$ip = trim($ip);  
				if ($ip != 'unknown'){  
					$realip = $ip;  
					break;  
				}  
			}  
		}else if(isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], $unknown))
			$realip = $_SERVER['HTTP_CLIENT_IP'];  
		else if(isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown))
			$realip = $_SERVER['REMOTE_ADDR'];  
		else
			$realip = $unknown;  
	}else{  
		if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), $unknown))
				$realip = getenv("HTTP_X_FORWARDED_FOR");  
		else if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), $unknown))
			$realip = getenv("HTTP_CLIENT_IP");  
		else if(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), $unknown))
			$realip = getenv("REMOTE_ADDR");  
		else
			$realip = $unknown;  
	}  
	$realip = preg_match("/[\d\.]{7,15}/", $realip, $matches) ? $matches[0] : $unknown;  
	return $realip;  
}  

function urlmessage($call, $icon, $dtmstr, $msg, $ddt) {
	global $mysqli;
	global $startdatestr;
	$m = "<font face=微软雅黑 size=2><img src=".$icon."> ".$call." <a href=".$_SERVER["PHP_SELF"]."?call=".$call." target=_blank>数据包</a> <a id=\\\"m\\\" href=\\\"#\\\" onclick=\\\"javascript:monitor_station('".$call."');return false;\\\">";
	$m = $m."切换跟踪</a> ";
	$m = $m."<a href=".$_SERVER["PHP_SELF"]."?track=".$call." target=_blank>轨迹下载</a> ";
	$m = $m."<hr color=green>".$dtmstr."<br>";

	$msg = rtrim($msg);
	if(strlen($msg)>=4) 
		if(substr($msg, strlen($msg)-4,4)=="/UDP")   // strip /UDP
			$msg = substr($msg,0, strlen($msg)-4);
	$msg = rtrim($msg);

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
		if(substr($msg,0,1)==',') {  // ,053,069,071,035,047,058,28.928 PM1.0 PM2.5 PM10 
			$msg = substr($msg,1);
			$uspm1 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$uspm25 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$uspm10 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$cnpm1 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$cnpm25 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$cnpm10 = 0 + $msg;
			$msg = strstr($msg,",");
			$msg = substr($msg,1);
			$jiaquan = 0 + $msg;
			$m = $m."<b>美标PM1.0/2.5/10</b>: ".$uspm1."/".$uspm25."/".$uspm10." ug/m3<br>";	
			$m = $m."<b>国标PM1.0/2.5/10</b>: ".$cnpm1."/".$cnpm25."/".$cnpm10." ug/m3<br>";	
			$m = $m."<b>甲醛</b>: ".$jiaquan." ug/m3<br>";
			while(1) {
				$c=substr($msg,0,1);
				if( is_numeric($c) || ($c=='.')) {
					$msg = substr($msg,1);
					continue;
				}
				break;
			}
		}
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
			$m=$m." 海拔".$alt."m";
			$msg = substr($msg,9);
		}
		$m = $m."</b><br>";
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
		if(strlen($msg)>=2) 
			if((substr($msg,strlen($msg)-2,2)=='_(') 
			|| (substr($msg,strlen($msg)-2,2)=='_)')
			|| (substr($msg,strlen($msg)-2,2)=='_"')
			 )
				$msg=substr($msg,0,strlen($msg)-2);
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
		
	if(strlen($msg)>0)
	$m = $m."</font><font color=green face=微软雅黑 size=2>".addcslashes(htmlspecialchars($msg),"\\\r\n'\"")."</font><br>";

 	$q = "select raw from aprspacket where tm>=? and `call` = ? and lat ='' order by tm desc limit 1";
	$stmt = $mysqli->prepare($q);
	if(!$stmt) {
		echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
		exit;
	}
        $stmt->bind_param("ss",$startdatestr,$call);
        $stmt->execute();
	$rawmsg = "";
       	$stmt->bind_result($rawmsg);
	$stmt->fetch();
	if($rawmsg!="") {
		$rawmsg = strstr($rawmsg,":>");
		if($rawmsg) {
			$rawmsg = substr($rawmsg,2);
			$t = strpos($rawmsg, "/UDP");
			if($t !== false )
				$rawmsg = substr($rawmsg, 0, $t);
			$m = $m."<font color=red face=微软雅黑 size=2>".addcslashes(htmlspecialchars($rawmsg),"\\\r\n'\"")."</font>";
		}
	}	
	$stmt->close();
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
	$starttm = microtime(true);
//删除10天前的每个台站最后状态数据包
	$q="delete from lastpacket where tm<=date_sub(now(),INTERVAL 10 day)";
	$mysqli->query($q);
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";

	$llon1=$_REQUEST["llon1"];	
	$llon2=$_REQUEST["llon2"];	
	$llat1=$_REQUEST["llat1"];	
	$llat2=$_REQUEST["llat2"];	
	$lon1=$_REQUEST["lon1"];	
	$lon2=$_REQUEST["lon2"];	
	$lat1=$_REQUEST["lat1"];	
	$lat2=$_REQUEST["lat2"];	

	$disp15min=$_REQUEST["disp15min"];
	$ldisp15min=$_REQUEST["ldisp15min"];

	$span=$_SESSION["span"];
	$lspan=$_REQUEST["lspan"];

	if( ($llon1==$lon1) && ($llon2==$lon2) && ($llat1==$lat1) && ($llat2==$lat2)) 
		$viewchanged=0;
	else  $viewchanged=1;
	if($disp15min!=$ldisp15min) 
		$viewchanged=1;
	if($span!=$lspan)
		$viewchanged=1;
	if($viewchanged) {
		$tm=0;  // get all new lastpacket
		echo "llon1=$lon1;\n";
		echo "llon2=$lon2;\n";
		echo "llat1=$lat1;\n";
		echo "llat2=$lat2;\n";
		echo "ldisp15min=$disp15min;\n";
		echo "disp15min_refresh = 0;\n";
		echo "lspan=$span;\n";
	}
	if(($tm==0) && ($disp15min=="true")) 
		$tm = time() - 15*60;
	if (isset($_REQUEST["call"]))  {
		$call=$_REQUEST["call"];
		$q="select lat,lon,`call`,unix_timestamp(tm),tm,concat(`table`,symbol),msg,datatype from lastpacket where (`call`=? or (tm>=FROM_UNIXTIME(?) and tm>=?) ) and lat<>'' and not lat like '0000.00%'";
		$stmt=$mysqli->prepare($q);
        	$stmt->bind_param("sis",$call,$tm,$startdatestr);
	} else {
		$q="select lat,lon,`call`,unix_timestamp(tm),tm,concat(`table`,symbol),msg,datatype from lastpacket where tm>=FROM_UNIXTIME(?) and tm>=? and lat<>'' and not lat like '0000.00%'";
		$stmt=$mysqli->prepare($q);
        	$stmt->bind_param("is",$tm,$startdatestr);
	}
        $stmt->execute();
       	$stmt->bind_result($glat,$glon,$dcall,$dtm,$dtmstr,$dts,$dmsg,$ddt);
	$stmt->store_result();
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
		if ( $lon < $lon1) continue;
		if ( $lon > $lon2) continue;
		if ( $lat < $lat1) continue;
		if ( $lat > $lat2) continue;
		$icon = "img/".bin2hex($dts).".png";
		$dmsg = urlmessage($dcall, $icon, $dtmstr, $dmsg,$ddt);
		echo "setstation(".$lon.",".$lat.",\"".$dcall."\",".$dtm.",\"".$icon."\",\n\"".$dmsg."\");\n";
	}
	$stmt->close();
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";

	$q="select count(*) from lastpacket where tm>=\"".$startdatestr."\"";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "updatecalls(".$r[0].");\n";
	$q="select sum(packets) from packetstats where day>=\"".$startdatestr."\"";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	$r[0]=intval($r[0]);
	echo "updatepkts(".$r[0].");\n";
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";
	
	echo "deloldstation(".time().");\n";

	if (!isset($_REQUEST["call"])) 
		exit(0);
	$call=$_REQUEST["call"];
	if($call=="") exit(0);

	if($span!=$lspan) { // 历史时间发生变化，删除所有路径, 重新更新
		echo "	if(movepath.length>0) { map.removeOverlay(polyline); movepath.splice(0,movepath.length); updatepathlen();} \n";
		$pathlen = 0;
	} else {
		$pathlen = @$_REQUEST["pathlen"];
		if($pathlen=="") $pathlen=0;
	}
	$q="select lat,lon from aprspacket where tm>? and `call`=? and lat<>'' and not lat like '0000.00%' order by tm limit 50000 offset ?";
        $stmt=$mysqli->prepare($q);
        $stmt->bind_param("ssi",$startdatestr,$call,$pathlen);
        $stmt->execute();
        $stmt->bind_result($glat, $glon);

	$pathmore=0;
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
		$pathmore=1;
                echo "addpathpoint(".$lon.",".$lat.");\n";
        }	
	if($pathmore==1) {
		echo "polyline.setPath(movepath);\n";
		echo "updatepathlen();\n";
		echo "if(autocenter)map.panTo(new BMap.Point(".$lon.",".$lat."));\n";
	}
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";
	exit(0);
}

function disp_map($call) {
	echo "<a href=\"http://aprs.fi/#!mt=roadmap&z=11&call=a%2F".$call."&timerange=43200&tail=43200\" target=_blank>aprs.fi</a> ";
	echo "<a href=\"http://aprs.hamclub.net/mtracker/map/aprs/".$call."\" target=_blank>hamclub</a> ";
	echo "<a href=\"http://aprs.hellocq.net/\" target=_blank>hellocq</a> ";
	echo "<a href=\"".$_SERVER["PHP_SELF"]."?map&call=".$call."\" target=_blank>本站</a> ";
}

function top_menu() {
	global $mysqli, $cmd;
	$blank="";
	if($cmd=="map") $blank = " target=_blank";
	echo "<a href=".$_SERVER["PHP_SELF"]."?new".$blank.">最新</a> <a href=".$_SERVER["PHP_SELF"]."?today".$blank.
	">今天</a> <a href=".$_SERVER["PHP_SELF"]."?stats".$blank.">统计</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?map target=_blank>地图</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?ge>地球</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?setup>设置</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?about>关于</a> ";
	echo "<form style=\"margin:0px;display: inline\" action=".$_SERVER["PHP_SELF"]." method=GET>";
	echo "<input name=today type=hidden>";
	echo "<input name=str size=6>";
	echo "<input type=submit value=查找>";
	echo "</form><p>";
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
		#allmap {position:absolute;top:25px;left:0; right:0px; bottom:0px; width: 100%;}
		#control{width:100%;}
		#ge { display:inline} 
		#calls { display:inline} 
		#inview { display:inline;} 
		#pkts { display:inline} 
		#msg { display:inline; color:green} 
		#pathlen { display:inline; color:green} 
		#autocenter { display:inline;} 
		#disp15min { display:inline;} 
		#search { display:inline;} 
	</style>
	<title><?php echo $title; ?></title>
	<script type="text/javascript" src="http://api.map.baidu.com/api?v=2.0&ak=<?php echo $ak;?>"></script>
</head>
<body>
<div id="full">
	<div id="top"><?php
	$blank = " target=_blank";
	echo "<a href=".$_SERVER["PHP_SELF"]."?new".$blank.">最新</a> <div id=ge><a href=".$_SERVER["PHP_SELF"]."?ge".$blank.
	">地球</a> </div>";
	echo "<a href=".$_SERVER["PHP_SELF"]."?setup target=_blank>设置</a> ";
	echo" <div id=calls></div><div id=inview></div><div id=pkts></div> ";
	echo "<div id=msg></div><div id=pathlen></div><div id=autocenter></div>";
	echo "<div id=disp15min></div>";
	echo " <div id=search><form style=\"margin:0px;display: inline\" action=".$_SERVER["PHP_SELF"]." method=GET>";
	echo "<input name=today type=hidden>";
	echo "<input name=str size=6>";
	echo "<input type=submit value=查找>";
	echo "</form></div>";
	echo "</div>";
?>
	<div id="allmap"></div>
</div>
</body>
<script type="text/javascript">
var totalmarkers=0;
var markers = {};
var lasttms = {};
var iconurls = {};
var infowindows = {};
var lastupdatetm=0;
var movepath = new Array();
var polyline;
var call="";
var ismobile=0;
var llon1=0;
var llon2=0;
var llat1=0;
var llat2=0;
var jiupian=1;
var autocenter=true;
var debug=true;
var disp15min = false;
var ldisp15min = false;
var disp15min_refresh = 0;
var lspan=1;

(function(a,b){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))ismobile=b})(navigator.userAgent||navigator.vendor||window.opera,1);

if(ismobile) {
	document.getElementById("ge").innerHTML = "";
	document.getElementById("disp15min").innerHTML = "";
}

function updateinview() {
	if(ismobile)
		document.getElementById("inview").innerHTML = "("+totalmarkers+")";
	else
		document.getElementById("inview").innerHTML = "(显示"+totalmarkers+")";
}

function updatepathlen() {
	if(ismobile)
		document.getElementById("pathlen").innerHTML = "/"+movepath.length;
	else
		document.getElementById("pathlen").innerHTML = "航点"+movepath.length;
}

function updatecalls(calls) {
	if(ismobile)
		document.getElementById("calls").innerHTML = calls+"C";
	else
		document.getElementById("calls").innerHTML = calls+"呼号";
}

function updatepkts(pkts) {
	if(ismobile)
		document.getElementById("pkts").innerHTML = "/"+pkts+"P";
	else
		document.getElementById("pkts").innerHTML = "/"+pkts+"数据包 ";
}

function autocenter_click(obj){
	autocenter = obj.checked;
	if(autocenter && movepath.length>0)  {
		map.panTo(movepath[movepath.length-1]);
       		map.setZoom(15);
	}
}

function disp15min_click(obj){
	disp15min = obj.checked;
}

function monitor_station(mycall) {
	if(movepath.length>0) {
		map.removeOverlay(polyline);
		movepath.splice(0,movepath.length);
		document.getElementById("pathlen").innerHTML = "";
	}	
	document.getElementById("msg").innerHTML = "";
	document.getElementById("autocenter").innerHTML = "";
	if(call==mycall) {
		call="";
		return;
	}
	call=mycall;
	if(ismobile) 
		document.getElementById("msg").innerHTML = call;
	else
		document.getElementById("msg").innerHTML = " 跟踪"+call;
	if(autocenter)
		document.getElementById("autocenter").innerHTML = "<input type=checkbox checked id=autocenter onclick=\"autocenter_click(this);\">航点居中</input>";
	else
		document.getElementById("autocenter").innerHTML = "<input type=checkbox id=autocenter onclick=\"autocenter_click(this);\">航点居中</input>";
       	map.setZoom(15);
}

function disp15min_div(){
	if(ismobile) 
		return;
	if(disp15min)
		document.getElementById("disp15min").innerHTML = "<input type=checkbox checked id=disp15min onclick=\"disp15min_click(this);\">仅显示活动站点</input>";
	else
		document.getElementById("disp15min").innerHTML = "<input type=checkbox id=disp15min onclick=\"disp15min_click(this);\">仅显示活动站点</input>";
}

function addpathpoint(lon, lat){
	var p = new BMap.Point(lon,lat);
	movepath.push (p);
	if(movepath.length==1) {
		polyline = new BMap.Polyline(movepath,{strokeColor:"blue", strokeWeight:3, strokeOpacity:0.5});
		map.addOverlay(polyline);
	}
}

function delstation(label) {
	map.removeOverlay(markers[label]);
	delete markers[label];
        delete lasttms[label];
        delete iconurls[label];
        delete infowindows[label];
	totalmarkers--;
	updateinview();
}

function deloldstation(tm) {
	if(!disp15min) {
		return;
	}
	if(disp15min_refresh % 10 != 0) {
		disp15min_refresh ++;
		return;
	}
	disp15min_refresh = 1;
	for(var label in markers) {
		if(call==label) 
			continue;
		if( lasttms[label]< tm-900 )
			delstation(label);
	}
}

function map_resize() {
	var b = map.getBounds();
        lon1=b.getSouthWest().lng;
	lat1=b.getSouthWest().lat;
	lon2=b.getNorthEast().lng;
	lat2=b.getNorthEast().lat;
	for(var label in markers) {
		if(call==label) 
			continue;
		p = markers[label].getPosition();
		if( (p.lng<lon1) || (p.lat<lat1) || (p.lng>lon2) || (p.lat>lat2))
			delstation(label);
	}
}

function setstation(lon, lat, label, tm, iconurl, msg)
{	
	if(markers.hasOwnProperty(label)) { 
		if(tm<lasttms[label]) return;
		markers[label].setAnimation(null);
		markers[label].setPosition( new BMap.Point(lon, lat) );
		infowindows[label].setContent(msg);
		if(iconurls[label]!=iconurl) {
			var nicon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 12)});
			markers[label].setIcon(nicon);
			iconurls[label]=iconurl;
		}
		if(tm==lasttms[label]) tm++;  // 如果同一个站点同样的时间戳第二次出现，说明至少过去了2秒，
					// 可以将最后时间戳+1, 这样一来，同一个站点的信息最多重复一次
		else {
			var m = markers[label];
    			m.setAnimation(BMAP_ANIMATION_BOUNCE);
			setTimeout( 
				function(){
					m.setAnimation(null);
				}, 
				500);
		}
		lasttms[label] = tm;
		if(tm>lastupdatetm) lastupdatetm = tm;
		updateinview();
		return;
	}
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
	if(tm>lastupdatetm) lastupdatetm = tm;
    	marker.setAnimation(BMAP_ANIMATION_BOUNCE);
	setTimeout(
		function(){
			marker.setAnimation(null);
		}, 
		500);
	totalmarkers++;
	updateinview();
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
        var url = window.location.protocol+"//"+window.location.host+"/"+window.location.pathname+"?tm="+lastupdatetm+"&call="+call+"&pathlen="+movepath.length+"&llon1="+llon1+"&llon2="+llon2+"&llat1="+llat1+"&llat2="+llat2+"&lon1="+b.getSouthWest().lng+"&lat1="+b.getSouthWest().lat+"&lon2="+b.getNorthEast().lng+"&lat2="+b.getNorthEast().lat+"&disp15min="+disp15min+"&ldisp15min="+ldisp15min;
	url = url+"&lspan="+lspan;
	if(jiupian!=1) url = url+"&jiupian="+jiupian;
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

function centertounknow(){
	var geolocation = new BMap.Geolocation();
	geolocation.getCurrentPosition(function(r){
		if(this.getStatus() == BMAP_STATUS_SUCCESS){
			map.centerAndZoom(r.point,12);
		}
	},{enableHighAccuracy: false});
	UpdateStation();  
	updateinview();
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
map.addEventListener('moveend', map_resize);
map.addEventListener('zoomend', map_resize);
map.addEventListener('resize', map_resize);

<?php
	echo "jiupian=$jiupian;\n";
        $call=@$_REQUEST["call"];
        if($call!="")  {
		echo "monitor_station(\"$call\");\n";
		echo "UpdateStation();\n";
	} else  {			// try IP location
		$IP = GetIP();
  		$content = file_get_contents("http://api.map.baidu.com/location/ip?ak=".$ak."&ip=".$IP."&coor=bd09ll");
  		$json = json_decode($content);
		if(isset($json->{'status'}) && ($json->{'status'}==0)) {
			echo "map.centerAndZoom(new BMap.Point(";
			echo $json->{'content'}->{'point'}->{'x'};
			echo ",";
			echo $json->{'content'}->{'point'}->{'y'};
			echo "),12); ";
			echo "UpdateStation();  updateinview();";
		}  else 
			echo "centertounknow();\n";
	}
	echo "disp15min_div()\n";
?>

</script>
</html>
<?php
	exit(0);
}

function gpx_wpt($tm, $msg, $ddt) {
	$alt = 0;
	$m = "";
	if( (strlen($msg)>=7) &&
		(substr($msg,3,1)=='/'))  // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=number_format(substr($msg,4,3)*1.852,1);
		$m = $m."<b>".$speed." km/h ".$dir."°";
		$msg = substr($msg,7);
		if( substr($msg,0,3)=='/A=') {      // 178/061/A=000033
			$alt=number_format(substr($msg,3,6)*0.3048,1);
		} 
		$m="<ele>$alt</ele><time>$tm</time><magvar>$dir</magvar><desc>$speed km/h</desc>";
		return $m;
	} else if( (strlen($msg)>=9) &&
		(substr($msg,0,3)=='/A=') )      // /A=000033
	{
		$alt=number_format(substr($msg,3,6)*0.3048,1);
		$m="<ele>$alt</ele><time>$tm</time>";
		return $m;
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
		$m="<ele>$alt</ele><time>$tm</time><magvar>$dir</magvar><desc>$speed km/h</desc>";
		return $m;
	}
	$m="<time>$tm</time>";
	return $m;
}

if($cmd=="track") {
	if($call=="") exit(0); 
	if(isset($_REQUEST["startdate"])) {
		$startdatestr = $_REQUEST["startdate"]." 00:00:00";
		$enddatestr = $_REQUEST["enddate"]." 23:59:59";
		if($_REQUEST["type"]=="gpx")
			download_gpx($call, $startdatestr, $enddatestr);
		else
			download_kml($call, $startdatestr, $enddatestr);
		exit(0);
	}
}


function download_gpx($call, $startdatestr, $enddatestr) {
	global $mysqli;
/*Content-Disposition:attachment; filename="2110153.gpx"
Content-Language:en-US
Content-Length:7890
Content-Transfer-Encoding:binary
Content-Type:application/gpx+xml
*/
	date_default_timezone_set("Asia/Shanghai");
	header("Content-Type:application/gpx+xml");
	header("Content-Disposition:attachment; filename=\"".$call."-".$startdatestr."Track.gpx\"");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<gpx version=\"1.0\">\n";
	echo "<name>APRS GPX</name>\n";
	echo "<trk><name>".$call." ".$startdatestr." Track</name><number>1</number><trkseg>\n";
	$q="select date_format(CONVERT_TZ(tm,@@session.time_zone, '+00:00'),\"%Y-%m-%dT%H:%i:%sZ\"),lat,lon,msg,datatype from aprspacket where tm>=? and tm<=? and `call`=? and lat<>'' and not lat like '0000.00%' order by tm";
        $stmt=$mysqli->prepare($q);
        $stmt->bind_param("sss",$startdatestr,$enddatestr,$call);
        $stmt->execute();
        $stmt->bind_result($dtm, $glat, $glon, $msg, $ddt);
//<trkpt lat="46.57608333" lon="8.89241667"><ele>2376</ele><time>2007-10-14T10:09:57Z</time></trkpt>
        while($stmt->fetch()) {
                $lat = strtolat($glat);
                $lon = strtolon($glon);
		$wpt = gpx_wpt($dtm, $msg,$ddt);
		echo "<trkpt lat=\"".$lat."\" lon=\"".$lon."\">".$wpt."</trkpt>\n";
        }	
	echo "</trkseg></trk>\n";
	echo "</gpx>\n";
	exit(0);
}

function kml_alt($msg) {
	$alt = 0;
	$m = "";
	if( (strlen($msg)>=7) &&
		(substr($msg,3,1)=='/'))  // 178/061/A=000033
	{
		$msg = substr($msg,7);
		if( substr($msg,0,3)=='/A=') {      // 178/061/A=000033
			$alt=number_format(substr($msg,3,6)*0.3048,1);
		} 
		return $alt;
	} else if( (strlen($msg)>=9) &&
		(substr($msg,0,3)=='/A=') )      // /A=000033
	{
		$alt=number_format(substr($msg,3,6)*0.3048,1);
		return $alt;
	} else if( ($ddt=='`')  &&
		 (strlen($msg)>=9) )   // `  0+jlT)v/]"4(}=
	{
		$msg = substr($msg,8);
		if((substr($msg,0,1)==']') || (substr($msg,0,1)=='`') )
			$msg=substr($msg,1);
		if( (strlen($msg)>=4) && (substr($msg,3,1)=='}') ) {
			$alt = (ord( substr($msg,0,1)) -33)*91*91+
				(ord( substr($msg,1,1)) -33)*91 +
				(ord( substr($msg,2,1)) -33) -10000;
			$alt = number_format($alt,1);
		}
		return $alt;
	}
	return $alt;
}

function kml_wpt($tm, $msg, $ddt) {
	$alt = 0;
	$m = "";
	if( (strlen($msg)>=7) &&
		(substr($msg,3,1)=='/'))  // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=number_format(substr($msg,4,3)*1.852,1);
		$m = $m."<b>".$speed." km/h ".$dir."°";
		$msg = substr($msg,7);
		if( substr($msg,0,3)=='/A=') {      // 178/061/A=000033
			$alt=number_format(substr($msg,3,6)*0.3048,1);
		} 
		$m="<ele>$alt</ele><time>$tm</time><magvar>$dir</magvar><desc>$speed km/h</desc>";
		return $m;
	} else if( (strlen($msg)>=9) &&
		(substr($msg,0,3)=='/A=') )      // /A=000033
	{
		$alt=number_format(substr($msg,3,6)*0.3048,1);
		$m="<ele>$alt</ele><time>$tm</time>";
		return $m;
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
		$m="<ele>$alt</ele><time>$tm</time><magvar>$dir</magvar><desc>$speed km/h</desc>";
		return $m;
	}
	$m="<time>$tm</time>";
	return $m;
}

function download_kml($call, $startdatestr, $enddatestr) {
	global $mysqli;
	date_default_timezone_set("Asia/Shanghai");
	header("Content-Type:application/gpx+xml");
	header("Content-Disposition:attachment; filename=\"".$call."-".$startdatestr."Track.kml\"");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\">\n";
	echo "<Document>\n";
	echo "<name>".$call." Track</name>\n";?>
	<Style id="ylw">
		<LabelStyle>
			<color>ff1307ff</color>
		</LabelStyle>
		<LineStyle>
			<color>ff00ffff</color>
			<width>4</width>
		</LineStyle>
	</Style>
<?php
	echo "<Folder><name>".$call."-".$startdatestr."</name>\n";
	echo "<Placemark>\n";
	echo "<styleUrl>#ylw</styleUrl>\n";
	echo "<gx:Track id=\"1\">\n";
	echo "<altitudeMode>absolute</altitudeMode>\n";
	$q="select date_format(CONVERT_TZ(tm,@@session.time_zone, '+00:00'),\"%Y-%m-%dT%H:%i:%sZ\"),lat,lon,msg,datatype from aprspacket where tm>=? and tm<=? and `call`=? and lat<>'' and not lat like '0000.00%' order by tm";
        $stmt=$mysqli->prepare($q);
        $stmt->bind_param("sss",$startdatestr,$enddatestr,$call);
        $stmt->execute();
        $stmt->bind_result($dtm, $glat, $glon, $msg, $ddt);
	$stmt->store_result();	
        while($stmt->fetch()) {
		echo "<when>".$dtm."</when>\n";
	}
	$stmt->data_seek(0);
        while($stmt->fetch()) {
                $lat = strtolat($glat);
                $lon = strtolon($glon);
//		$wpt = gpx_wpt($dtm, $msg,$ddt);
		echo "<gx:coord>".$lon." ".$lat." ";
		echo kml_alt($msg);
		echo "</gx:coord>\n";
        }	
	echo "</gx:Track>\n";
	echo "</Placemark>\n";
	echo "</Folder>\n";
	echo "</Document>\n";
	echo "</kml>\n";
	exit(0);
}
?>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title; ?></title>
</head>
<style type="text/css">
<!--
div{ display:inline} 
-->
</style>

<body bgcolor=#dddddd>

<?php

top_menu();

if($cmd=="track") {
	$startdate=date_create();
	$startdatestr=date_format($startdate,"Y-m-d");

	echo "<h3>下载 ".$call." 轨迹</h3>";
	echo "<form action=".$_SERVER["PHP_SELF"]." method=GET>";
	echo "<input type=hidden name=track value=\"".$call."\">";
	echo "请选择开始时间： ";
	echo "<input name=startdate type=date value=".$startdatestr."><p>";
	echo "请选择结束时间： ";
	echo "<input name=enddate type=date value=".$startdatestr."><p>";
	echo "请选择轨迹类型： GPX格式<input type=radio name=type value=gpx>  KML格式<input type=radio checked name=type value=kml><p>";
	echo "<input type=submit value=\"下载\">";
	exit(0);
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
		echo $r[2];  //raw
        	echo "</td><td>";
		disp_map($r[1]);
        	echo "</td></tr>\n";
	}
	echo "</table>\n";

	echo "<h3>最新收到的无法解析经纬度的APRS数据包</h3>";
	$q="select tm,`call`,raw from aprspacket where tm>=curdate() and lat='' order by tm desc limit 10";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th>时间</th><th>呼号</th><th>APRS Packet</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo $r[0];
        	echo "</td><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[1]>$r[1]</a>";
        	echo "</td><td>";
		echo $r[2];  //raw
        	echo "</td></tr>\n";
	}
	echo "</table>\n";


	echo "<h3>最新收到的气象站APRS数据包</h3>";
	$q="select tm,`call`,raw from aprspacket where tm>=curdate() and `call` like '%-13' order by tm desc limit 10";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th>时间</th><th>呼号</th><th>APRS Packet</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo $r[0];
        	echo "</td><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[1]>$r[1]</a>";
        	echo "</td><td>";
		echo $r[2];  //raw
        	echo "</td></tr>\n";
	}
	echo "</table>\n";
	exit(0);
}

if ($cmd=="today") {
	if(isset($_REQUEST["str"])) {
		$str = $_REQUEST["str"];
		echo "<h3>今天收到的 <a href=".$_SERVER["PHP_SELF"]."?track=".$str.">$str</a> 有关APRS数据包 ";
	} else {
		echo "<h3>今天收到的APRS数据包 ";
		$str = "";
	}
	$str = "%".$str."%";
	$q = "select count(distinct(`call`)), count(*) from aprspacket where tm>curdate() and ( `call` like ? or raw like ?) ";
	$stmt=$mysqli->prepare($q);
	$stmt->bind_param("ss",$str,$str);
	$stmt->execute();
	$stmt->bind_result($r[0],$r[1]);
	$stmt->store_result();	
	$stmt->fetch();
	echo "<font color=blue>";
	echo $r[0]."呼号/".$r[1]."数据包";
	echo "</font>";
	echo "</h3>";
	$stmt->close();

	if(isset($_REQUEST["c"]))
		$q = "select `call`, count(*) c, count(distinct(concat(lon,lat))) from aprspacket where tm>curdate() and (`call` like ? or raw like ?) group by `call` order by c desc";
	else if(isset($_REQUEST["d"]))
		$q = "select `call`, count(*), count(distinct(concat(lon,lat))) c from aprspacket where tm>curdate() and (`call` like ? or raw like ?) group by `call` order by c desc";
	else
		$q = "select `call`, count(*), count(distinct(concat(lon,lat))) from aprspacket where tm>curdate() and (`call` like ? or raw like ?) group by `call`";
	$stmt=$mysqli->prepare($q);
	$stmt->bind_param("ss",$str,$str);
	$stmt->execute();
	$stmt->bind_result($r[0],$r[1],$r[2]);
	$stmt->store_result();	
	echo "<table border=1 cellspacing=0><tr><th><a href=".$_SERVER["PHP_SELF"]."?today>呼号</a></th>";
	echo "<th><a href=".$_SERVER["PHP_SELF"]."?today&c>数据包数量</a></th>";
	echo "<th><a href=".$_SERVER["PHP_SELF"]."?today&d>位置点数量</a></th><th>下载轨迹</th><th>地图</th></tr>\n";
	while($stmt->fetch()) {
        	echo "<tr><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[0]>$r[0]</a>";
        	echo "</td><td align=right>";
        	echo $r[1];
        	echo "</td><td align=right>";
        	echo $r[2];
        	echo "</td><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?track=$r[0]>下载轨迹</a>";
        	echo "</td><td>";
		disp_map($r[0]);
        	echo "</td></tr>\n";
	}
	echo "</table>";
	exit(0);
}

if ($cmd=="call") {
	echo "今天收到的 $call ";
	$q="select tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw from aprspacket where tm>curdate() and `call`=? order by tm desc";
	$stmt=$mysqli->prepare($q);
        $stmt->bind_param("s",$call);
        $stmt->execute();
	$stmt->store_result();
	$meta = $stmt->result_metadata();
	echo $stmt->num_rows;

	echo " APRS数据包 ";
       	echo "<a href=".$_SERVER["PHP_SELF"]."?track=$call>下载轨迹</a> ";
	disp_map($call);
	echo "<p>";

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
		echo $r[8];  //raw
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

if ($cmd=="ge") {
	echo "<h3>选择Google Earth参数</h3> ";
	$span = $_SESSION["span"];
	echo "<form action=ge.php method=POST>";
	echo "<input name=kml type=hidden>";
	echo "显示历史天数：";
	echo "<select name=span>";
	for ( $i=1; $i<8; $i++) {
		if ( $i==$span )
			echo "<option value=\"$i\" selected=\"selected\">".$i."天</option>";
		else
			echo "<option value=\"$i\">".$i."天</option>";
	}
	echo "</select>";
	echo " 选择2天，则显示从昨天00:00开始的台站数据和轨迹<p>";
	echo "数据更新间隔：";
	echo "<select name=interval>";
	echo "<option value=\"0\">不自动刷新</option>";
	echo "<option value=\"30\">30秒钟</option>";
	echo "<option value=\"60\" selected=\"selected\">60秒钟</option>";
	echo "<option value=\"120\">2分钟</option>";
	echo "</select> 在Google Earth停留时数据更新间隔<p>";
	echo "忽略高度数据：<input name=alt type=checkbox checked> 显示路径时忽略高度数据，贴地面<p>\n";
	echo "仅显示视线内：<input name=inview type=checkbox> 显示视线经纬度±0.5°内站点，避免超过屏幕对象限制<p>\n";
	echo "路径显示优化：<input name=opt type=checkbox> 放大后才显示路径，提高速度\n";
	echo "<p><input type=submit value=\"启动Google Earth\">";
	echo "<p>";
	echo "</form>";
	exit(0);
}

if ($cmd=="setup") {
	echo "<h3>轨迹历史时间</h3> ";
	if ( isset($_REQUEST["span"]) && isset($_REQUEST["spanchange"])) {
		$span = intval($_REQUEST["span"]);
		if ( ($span<=0) || ($span >10) ) $span=1;
		$_SESSION["span"] = $span;
	}
	$span = $_SESSION["span"];
	echo "<form action=".$_SERVER["PHP_SELF"]." method=POST>";
	echo "<input name=setup type=hidden>";
	echo "<input name=spanchange type=hidden>";
	echo "请选择轨迹历史:";
	echo "<select name=span>";
	for ( $i=1; $i<8; $i++) {
		if ( $i==$span )
			echo "<option value=\"$i\" selected=\"selected\">".$i."天</option>";
		else
			echo "<option value=\"$i\">".$i."天</option>";
	}
	echo "</select><br>";
	echo "<input type=submit value=\"设置轨迹历史时间\">";
	echo "<p>";
	echo "注：选择2天，则显示从昨天00:00开始的台站数据和轨迹<p>";
	echo "</form>";

	echo "<h3>显示时纠偏处理</h3>";
	if ( isset($_REQUEST["jiupian"]) && isset($_REQUEST["jiupianchange"])) {
		$jiupian = intval($_REQUEST["jiupian"]);
		if ( ($jiupian<0) || ($jiupian >2) ) $jiupian=1;
		$_SESSION["jiupian"] = $jiupian;
	}
	$jiupian = $_SESSION["jiupian"];
	echo "<form action=".$_SERVER["PHP_SELF"]." method=POST>";
	echo "<input name=setup type=hidden>";
	echo "<input name=jiupianchange type=hidden>";
	echo "请选择显示纠偏处理方式:<br>";
	echo "<select name=jiupian>";
	echo "<option value=0";
	if ($jiupian==0) echo " selected=\"selected\"";
	echo ">不处理，直接显示</option>";
	echo "<option value=1";
	if ($jiupian==1) echo " selected=\"selected\"";
	echo ">GPS坐标转换成百度坐标显示，默认方式</option>";
	echo "<option value=2";
	if ($jiupian==2) echo " selected=\"selected\"";
	echo ">火星坐标转换成百度坐标显示</option>";
	echo "</select>";
	echo "<br><input type=submit value=\"设置显示纠偏方式\">";
	echo "</form>";
	echo "修改本项设置，需手动刷新地图才生效";
	echo "<p>\n";
	echo "<h3>坐标转换工具</h3>";
	if(isset($_REQUEST["lat"])) {
		$lati=$_REQUEST["lat"];
		$loni=$_REQUEST["lon"];
		echo "输入地球纬度/经度：$lati/$loni<br>";
	require "wgtochina_baidu.php";
	$mp=new Converter();
		
		$lat = explode(".",$lati);
		if(count($lat)<=2) 
			$latui = $lati;
		else if(strlen($lat[2])==3)
			$latui = $lat[0] + ($lat[1]+$lat[2]/100)/100;
		else
			$latui = $lat[0] + $lat[1]/60+$lat[2]/3600;

		$lon = explode(".",$loni);
		if(count($lon)<=2) 
			$lonui = $loni;
		else if(strlen($lon[2])==3)
			$lonui = $lon[0] + ($lon[1]+$lon[2]/100)/100;
		else
			$lonui = $lon[0] + $lon[1]/60 +$lon[2]/3600;

		echo "归一化后纬度/经度：$latui/$lonui<br>";
		
			$p=$mp->WGStoBaiDuPoint($lonui,$latui);
			$lon = $p->getX();
			$lat = $p->getY();
		echo "百度地图纬度/经度：$lat/$lon<p>";
	}
	echo "<form action=".$_SERVER["PHP_SELF"]." method=POST>";
	echo "<input name=setup type=hidden>";
	echo "请按照如下3种格式之一输入（判断依据是最后小数点后数字位数）<br>\n";
	echo "ddd.ddddd，度.度的十进制小数部分（5位）例如：31.12035º<br>";
	echo "ddd.mm.mmm，度.分.分的十进制小数部分（3位）例如 31º10.335'<br>";
	echo "ddd.mm.ss, 度.分.秒 例如 31º12'42<p>";

	echo "GPS纬度: <input name=lat value=\"".$lati."\">(正数是北纬)<br>";
	echo "GPS经度: <input name=lon value=\"".$loni."\">(正数是东经)<br>";
	echo "<input type=submit value=转换>\n";
	echo "</form>";	
	exit(0);
}
if ($cmd=="about") {
	include "about.html";
	exit(0);
}
?>
