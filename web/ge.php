<?php

$colors = array("FF1400FF","FF14F000","FF14F0FF","FF78FF00","FFFF78F0","FF0078F0");
$colorindex=0;

include "db.php";

date_default_timezone_set( 'Asia/Shanghai');

$urlf = "http://".$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"];
$po = strripos($urlf,"/");
$baseurl = substr($urlf,0,$po+1);

$span = intval(@$_REQUEST["span"]);

if ( ($span<=0) || ($span>10) ) $span = 2;  // default 2 days data

$opt = 0;
if ( isset($_REQUEST["opt"])) 
	$opt = 1;

$inview = 0;
if ( isset($_REQUEST["inview"])) 
	$inview = 1;

$altmode = 0;		// GPS 高度
if ( isset($_REQUEST["alt"])) 
	$altmode = 1;   // 地面位置

$interval = 60;  // 默认60秒钟刷新
if ( isset($_REQUEST["interval"])) 
	$interval = intval($_REQUEST["interval"]);
if ( $interval < 0 ) $interval = 60;

if (isset($_REQUEST["kml"])) {
//	header("Content-Type:application/gpx+xml");
	header("Content-Type:application/vnd.google-earth.kml+xml");
	header("Content-Disposition:attachment; filename=\"ge.kml\"");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
	echo "<kml xmlns=\"http://earth.google.com/kml/2.1\">\n";
	echo "<NetworkLink>\n";
	echo "<visibility>1</visibility>\n";
	echo "<name>APRS objects</name>\n";
//	echo "<description><![CDATA[\n";
//	echo "<p>APRS object of server ".$_SERVER["HTTP_HOST"]."</p>\n";
//	echo "]]></description>\n";
	echo "<Link>\n";
	echo "<href>".$urlf."?span=".$span;
	if ($opt==1) echo "&amp;opt=1";
	if ($inview==1) echo "&amp;inview=1";
	if ($altmode==1) echo "&amp;alt=1";
	echo "</href>\n";
	echo "<viewRefreshMode>onStop</viewRefreshMode>\n";
	echo "<viewRefreshTime>1</viewRefreshTime>\n";
	 if ( $interval == 0 ) {
		echo "<refreshMode>onExpire</refreshMode>\n";
	} else {
		echo "<refreshMode>onInterval</refreshMode>\n";
		echo "<refreshInterval>".$interval."</refreshInterval>";
	}
	echo "</Link>\n";
	echo "</NetworkLink>\n";
	echo "</kml>\n";
	exit(0);
}

$span--;
$startdate=date_create();
date_sub($startdate,date_interval_create_from_date_string("$span days"));
$startdatestr=date_format($startdate,"Y-m-d 00:00:00");

$disppath=0;

if (isset($_REQUEST["BBOX"])) {
	$bbox=$_REQUEST["BBOX"];
	$ll = split(",",$bbox);
	$lon1 = $ll[0];
	$lon2 = $ll[2];
	$lat1 = $ll[1];
	$lat2 = $ll[3];
} else {
	$lon1=0;
	$lon2=180;
	$lat1=0;
	$lat2=90;
}

if(abs($lon2-$lon1)<=15)
	$disppath=1;

if($opt==0) 
	$disppath=1;

function urlmessage($call,$icon, $dtmstr, $msg, $ddt) {
	global $baseurl;
	$m = "<img src=".$baseurl.$icon."><a href=".$baseurl."index.php?call=".$call." target=_blank>数据包</a> ";
	$m =$m."轨迹";
	$m = $m."<a href=".$baseurl."index.php?gpx=".$call." target=_blank>GPX</a> ";
	$m = $m."<a href=".$baseurl."index.php?kml=".$call." target=_blank>KML</a> <hr color=green>".$dtmstr."<br>";
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
	 	$m = $m."雨".$r."mm/1h ".$p."mm/24h<b><br>";
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
	 	$m = $m."雨".$r."mm/自午夜起<b><br>";
	}
	if( (strlen($msg)>=7) &&
		(substr($msg,3,1)=='/'))  // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=number_format(substr($msg,4,3)*1.852,1);
		$m = $m."<b>".$speed." km/h ".$dir."°";
		$msg = substr($msg,7);
		if( substr($msg,0,3)=='/A=') {      // 178/061/A=000033
			$alt=number_format(substr($msg,3,6)*0.3048,1);
			$m=$m." 海拔".$alt."m</b><br>";
			$msg = substr($msg,9);
		}
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
		
	$m = $m."<font color=green>".addcslashes(htmlspecialchars($msg),"\\\r\n'\"")."</font>";
	return $m;	
}

function checklatlon($glat, $glon) {
	$s = substr($glat,7,1);
	if( $s!='S' && $s!='N' ) return false;
	$s = substr($glon,8,1);
	if( $s!='W' && $s!='E' ) return false;
	return true;
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

function kml_alt($msg, $ddt) {
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

	
header("Content-Type:application/vnd.google-earth.kml+xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\">\n";
echo "<Document>\n";
$q="select count(*) from lastpacket where tm>?";
$stmt=$mysqli->prepare($q);
$stmt->bind_param("s",$startdatestr);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->store_result();	
$stmt->fetch();
echo "<name>".$cnt." stations</name>\n";
$stmt->close();

echo "<Snippet>tracks during past 2 days</Snippet>\n";
$q="select distinct(concat(`table`,symbol)) from lastpacket where tm>?";
$stmt=$mysqli->prepare($q);
$stmt->bind_param("s",$startdatestr);
$stmt->execute();
$stmt->bind_result($dts);
$stmt->store_result();	
while($stmt->fetch()) {
	echo "<Style id=\"st";
	echo bin2hex($dts);
	echo "\">\n";
  	echo "<LabelStyle><color>".$colors[$colorindex]."</color><scale>1</scale></LabelStyle>\n";
	echo "<IconStyle><Icon><href>";
	echo $baseurl;
	echo "img/".bin2hex($dts).".png</href>";
    	echo "</Icon><scale>1</scale></IconStyle>\n";
  	echo "<LineStyle>\n";
    	echo "<color>";
	echo $colors[$colorindex];
	$colorindex++;
	if($colorindex==count($colors)) $colorindex=0;
	echo "</color>\n";
    	echo "<colorMode>normal</colorMode>\n";
    	echo "<width>4</width>\n";
  	echo "</LineStyle>\n";
//  	echo "<PolyStyle><color>7f00ff00</color></PolyStyle>\n";
	echo "</Style>\n";
}
$stmt->close();

$q="select `call`,date_format(CONVERT_TZ(tm,@@session.time_zone, '+00:00'),\"%Y-%m-%dT%H:%i:%sZ\"),lat,lon,msg,datatype,concat(`table`,symbol) from  lastpacket where tm>? and lat<>'' and not lat like '0000.00%' order by `call`";
$stmt=$mysqli->prepare($q);
$stmt->bind_param("s",$startdatestr);
$stmt->execute();
$stmt->bind_result($call, $dtm, $glat, $glon, $msg, $ddt, $dts);
$stmt->store_result();	
while($stmt->fetch()) {
	
	if(!checklatlon($glat, $glon)) continue; 
        $lat = strtolat($glat);
        $lon = strtolon($glon);
	if($inview==1) {
		if($lat<$lat1-0.5) continue;
		if($lat>$lat2+0.5) continue;
		if($lon<$lon1-0.5) continue;
		if($lon>$lon2+0.5) continue;
	}
	echo "<Placemark>\n";
	echo "  <name>".$call."</name>\n";
	echo "  <description><![CDATA[\n";
	echo urlmessage($call,"img/".bin2hex($dts).".png", $dtm, $msg, $ddt) ;
	echo "\n";
	echo "]]></description>\n";
	echo "  <Snippet maxLines='0'></Snippet>\n";
	echo "  <styleUrl>#st".bin2hex($dts)."</styleUrl>\n";
	echo "  <MultiGeometry>\n";
	echo "  <Point>\n";
	if ( $altmode == 0 ) {  // GPS 高度
		echo "  <altitudeMode>absolute</altitudeMode>\n";
		echo "    <coordinates>".$lon.",".$lat.",";
		echo kml_alt($msg,$ddt);
	} else {
		echo "  <altitudeMode>clampToGround</altitudeMode>\n";
		echo "    <coordinates>".$lon.",".$lat.",0";
	}
	echo "</coordinates>\n";
  	echo "  </Point>\n";

if($disppath==1) {
	$q = "select concat(lat,lon) a,lat,lon,msg,datatype from aprspacket where tm>? and `call`=? and lat<>'' and not lat like '0000.00%' group by a order by `tm`";
	$stmt2=$mysqli->prepare($q);
	$stmt2->bind_param("ss",$startdatestr,$call);
	$stmt2->execute();
	$stmt2->bind_result($nouse, $glat, $glon, $msg, $ddt);
	$stmt2->store_result();	
	if($stmt2->num_rows>1) {
		echo "<LineString>\n";
    		echo "  <tessellate>1</tessellate>\n";
		echo "  <extrude>1</extrude>\n";
		if ( $altmode == 0 )   // GPS 高度
    			echo "  <altitudeMode>absolute</altitudeMode>\n";
		else 
    			echo "  <altitudeMode>clampToGround</altitudeMode>\n";
		
		echo "  <coordinates>\n";
		while($stmt2->fetch()) {
			if(!checklatlon($glat, $glon)) continue; 
        		$lat = strtolat($glat);
        		$lon = strtolon($glon);
			echo $lon.",".$lat.",";
			if ( $altmode == 0 )   // GPS 高度
				echo kml_alt($msg,$ddt);
			else 
				echo "0";
			echo " ";
		}
		echo "  </coordinates>\n";
		echo "  </LineString>\n";
	}
}
	echo "  </MultiGeometry>\n";
	echo "</Placemark>\n";
}
?>
</Document>

<NetworkLinkControl>
    <linkName>APRS objects</linkName>
    <minRefreshPeriod>1</minRefreshPeriod>
</NetworkLinkControl>
</kml>
