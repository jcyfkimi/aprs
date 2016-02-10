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

function urlmessage($call,$icon, $dtmstr, $msg, $ddt) {
	$m = "<font face=微软雅黑 size=2><img src=".$icon."> ".$call." <a href=".$_SERVER["PHP_SELF"]."?call=".$call." target=_blank>数据包</a> <a id=\\\"m\\\" href=\\\"#\\\" onclick=\\\"javascript:monitor_station('".$call."');return false;\\\">";
	$m = $m."切换跟踪</a> <hr color=green>".$dtmstr."<br>";
	if( (strlen($msg)>=16) &&
		(substr($msg,3,1)=='/') &&
		(substr($msg,7,3)=='/A=') )      // 178/061/A=000033
	{
		$dir=substr($msg,0,3);
		$speed=number_format(substr($msg,4,3)*1.852,1);
		$alt=number_format(substr($msg,10,6)*0.3048,1);
		$m = $m."<b>".$speed." km/h ".$dir."° 海拔".$alt."m</b><br>";
		$msg = substr($msg,16);
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
	}  else if (  (strlen($msg)>=32) &&
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
		
	$msg=iconv("utf-8","gb2312",$msg); 
	$msg=rtrim($msg);
		
	$m = $m."</font><font color=green face=微软雅黑 size=2>".htmlspecialchars($msg)."</font>";
	return $m;	
}

if ($cmd=="tm") {
	$starttm = microtime(true);
	$q="delete from lastpacket where tm<=curdate()";
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

	if( ($llon1==$lon1) && ($llon2==$lon2) && ($llat1==$lat1) && ($llat2==$lat2)) 
		$viewchanged=0;
	else  {
		$viewchanged=1;
		$tm=0;  // get all new lastpacket
		echo "llon1=$lon1;\n";
		echo "llon2=$lon2;\n";
		echo "llat1=$lat1;\n";
		echo "llat2=$lat2;\n";
	}

	$q="select lat,lon,`call`,unix_timestamp(tm),tm,concat(`table`,symbol),msg,datatype from lastpacket where tm>=FROM_UNIXTIME(?) and lat<>'' and not lat like '0000.00%'";
	$stmt=$mysqli->prepare($q);
        $stmt->bind_param("i",$tm);
        $stmt->execute();
       	$stmt->bind_result($glat,$glon,$dcall,$dtm,$dtmstr,$dts,$dmsg,$ddt);

	while($stmt->fetch()) {
                $lat = substr($glat,0,2) + substr($glat,2,5)/60;
                $lon = substr($glon,0,3) + substr($glon,3,5)/60;
		if($jiupian) {
			$p=$mp->getBDEncryPoint($lon,$lat);
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

	$q="select count(*) from lastpacket where tm>=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "updatecalls(".$r[0].");\n";
	$q="select packets from packetstats where day=curdate()";
	$result = $mysqli->query($q);
	$r=$result->fetch_array();
	echo "updatepkts(".$r[0].");\n";
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";

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
//		echo "if(movepath.length>0) map.removeOverlay(polyline);\n";
//		echo "polyline = new BMap.Polyline(movepath,{strokeColor:\"blue\", strokeWeight:3, strokeOpacity:0.5});\n";
//		echo "map.addOverlay(polyline);\n";
		echo "polyline.setPath(movepath);\n";
		echo "updatepathlen();\n";
		echo "map.panTo(new BMap.Point(".$lon.",".$lat."));\n";
	}
	$endtm = microtime(true); $spantm = $endtm-$starttm; $startm=$endtm; echo "//".$spantm."\n";
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
	echo "<a href=".$_SERVER["PHP_SELF"]."?new".$blank.">最新</a> <a href=".$_SERVER["PHP_SELF"]."?today".$blank.
	">今天</a> <a href=".$_SERVER["PHP_SELF"]."?stats".$blank.">统计</a> ";
	echo "<a href=".$_SERVER["PHP_SELF"]."?map target=_blank>地图</a> <div id=calls></div><div id=pkts></div> ";
	echo " <a href=".$_SERVER["PHP_SELF"]."?about target=_blank>关于</a>&nbsp;&nbsp;<div id=msg></div><div id=pathlen></div><p>";
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
		#msg { display:inline; color:green} 
		#pathlen { display:inline; color:green} 
	</style>
	<title>APRS地图</title>
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
var infowindows = new Array();
var totalmarkers=0;
var lastupdatetm=0;
var movepath = new Array();
var polyline;
var call="";
var ismobile=0;
var llon1=0;
var llon2=0;
var llat1=0;
var llat2=0;
var jiupian=0;

(function(a,b){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))ismobile=b})(navigator.userAgent||navigator.vendor||window.opera,1);

function updatepathlen() {
	if(ismobile)
		document.getElementById("pathlen").innerHTML = "/"+movepath.length;
	else
		document.getElementById("pathlen").innerHTML = "路径点数"+movepath.length;
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
		document.getElementById("pkts").innerHTML = "/"+pkts+"数据包";
}

function monitor_station(mycall) {
	if(movepath.length>0) {
		map.removeOverlay(polyline);
		movepath.splice(0,movepath.length);
		document.getElementById("pathlen").innerHTML = "";
	}	
	document.getElementById("msg").innerHTML = "";
	if(call==mycall) {
		call="";
		return;
	}
	call=mycall;
	if(ismobile)
		document.getElementById("msg").innerHTML = ""+call;
	else
		document.getElementById("msg").innerHTML = "跟踪"+call;
       	map.setZoom(15);
}

function addpathpoint(lon, lat){
	var p = new BMap.Point(lon,lat);
	movepath.push (p);
	if(movepath.length==1) {
		polyline = new BMap.Polyline(movepath,{strokeColor:"blue", strokeWeight:3, strokeOpacity:0.5});
		map.addOverlay(polyline);
	}
}

function setstation(lon, lat, label, tm, iconurl, msg)
{	
	var i=labels.indexOf(label);   // 很旧的浏览器可能不支持, 如IE6/7/8
	if(i>-1) {
		if(tm<lasttms[i]) return;
		markers[i].setPosition( new BMap.Point(lon, lat) );
		infowindows[i].setContent(msg);
		if(iconurls[i]!=iconurl) {
			var nicon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 12)});
			markers[i].setIcon(nicon);
			iconurls[i]=iconurl;
		}
		markers[i].setZIndex(this.maxZindex++);
		if(tm==lasttms[i]) tm++;  // 如果同一个站点同样的时间戳第二次出现，说明至少过去了2秒，
					// 可以将最后时间戳+1, 这样一来，同一个站点的信息最多重复一次
		else {
    			markers[i].setAnimation(BMAP_ANIMATION_BOUNCE);
			setTimeout(function(){ markers[i].setAnimation(null); }, 500);
		}
		lasttms[i] = tm;
		if(tm>lastupdatetm) lastupdatetm = tm;
		return;
	}
	var icon = new BMap.Icon(iconurl, new BMap.Size(24, 24), {anchor: new BMap.Size(12, 12)});	
	var marker = new BMap.Marker(new BMap.Point(lon,lat), {icon: icon});
	marker.setLabel(new BMap.Label(label, {offset: new BMap.Size(20,-10)}));
	var infowindow = new BMap.InfoWindow(msg, {width:300});
	(function(){
        	marker.addEventListener('click', function(){
            	this.openInfoWindow(infowindow);
        	});
	})();
	map.addOverlay(marker);
	markers[totalmarkers]= marker;
	labels[totalmarkers] = label;
	lasttms[totalmarkers] = tm;
	iconurls[totalmarkers]=iconurl;
	infowindows[totalmarkers]=infowindow;
	if(tm>lastupdatetm) lastupdatetm = tm;
	totalmarkers=totalmarkers+1;
	marker.setZIndex(this.maxZindex++);
    	marker.setAnimation(BMAP_ANIMATION_BOUNCE);
	setTimeout(function(){ marker.setAnimation(null); }, 500);
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
        var url = '<?php echo "http://".$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"]."?tm=";?>'+lastupdatetm+"&call="+call+"&pathlen="+movepath.length+"&llon1="+llon1+"&llon2="+llon2+"&llat1="+llat1+"&llat2="+llat2+"&lon1="+b.getSouthWest().lng+"&lat1="+b.getSouthWest().lat+"&lon2="+b.getNorthEast().lng+"&lat2="+b.getNorthEast().lat;
	if(jiupian==0) url = url+"&nojiupian";
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
map.centerAndZoom(new BMap.Point(108.940178,34.5), 6);
<?php
	echo "jiupian=$jiupian;\n";
        $call=@$_REQUEST["call"];
        if($call!="")  {
		echo "monitor_station(\"$call\");\n";
	}
?>
createXmlHttpRequest();  
UpdateStation();  
</script>
<?php
	exit(0);
}
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
		$q = "select `call`, count(*) c, count(distinct(concat(lon,lat))) from aprspacket where tm>curdate() group by `call` order by c desc";
	else if(isset($_REQUEST["d"]))
		$q = "select `call`, count(*), count(distinct(concat(lon,lat))) c from aprspacket where tm>curdate() group by `call` order by c desc";
	else
		$q = "select `call`, count(*), count(distinct(concat(lon,lat))) from aprspacket where tm>curdate() group by substr(`call`,3)";
	$result = $mysqli->query($q);
	echo "<table border=1 cellspacing=0><tr><th><a href=".$_SERVER["PHP_SELF"]."?today>呼号</a></th>";
	echo "<th><a href=".$_SERVER["PHP_SELF"]."?today&c>数据包数量</a></th>";
	echo "<th><a href=".$_SERVER["PHP_SELF"]."?today&d>位置点数量</a></th><th>地图</th></tr>\n";
	while($r=$result->fetch_array()) {
        	echo "<tr><td>";
        	echo "<a href=".$_SERVER["PHP_SELF"]."?call=$r[0]>$r[0]</a>";
        	echo "</td><td align=right>";
        	echo $r[1];
        	echo "</td><td align=right>";
        	echo $r[2];
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

if ($cmd=="about") {
include "about.html";
}
?>
