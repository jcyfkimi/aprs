<?php

class Point {
    private $longitude;
    private $latitude;
    private $x;
    private $y;

    public function setX( $x ) {
        $this->x = $x;
    }

    public function getX() {
        return $this->x;
    }

    public function setY( $y ) {
        $this->y = $y;
    }

    public function getY() {
        return $this->y;
    }

    public function setLongitude( $longitude ) {
        $this->longitude = $longitude;
    }

    public function setLatitude( $latitude ) {
        $this->latitude = $latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getLatitude() {
        return $this->latitude;
    }

}

class Converter {
    public $casm_rr = 0;
    public $casm_t1 = 0;
    public $casm_t2 = 0;
    public $casm_x1 = 0;
    public $casm_y1 = 0;
    public $casm_x2 = 0;
    public $casm_y2 = 0;
    public $casm_f = 0;

    public function __construct() {
        $this->casm_rr = 0;
        $this->casm_t1 = 0;
        $this->casm_t2 = 0;
        $this->casm_x1 = 0;
        $this->casm_y1 = 0;
        $this->casm_x2 = 0;
        $this->casm_y2 = 0;
        $this->casm_f = 0;
    }

// WGS -> China
    public function getEncryPoint( $x, $y ) {
        $point = new Point();
        $x1;
        $tempx;
        $y1;
        $tempy;
        $x1 = $x * 3686400.0;
        $y1 = $y * 3686400.0;
        $gpsWeek = 0;
        $gpsWeekTime = 0;
        $gpsHeight = 0;

        $point = $this->wgtochina_lb( 1, (int)$x1, (int)$y1, (int)$gpsHeight, (int)$gpsWeek, (int)$gpsWeekTime );
        $tempx = $point->getX();
        $tempy = $point->getY();
        $tempx = $tempx / 3686400.0;
        $tempy = $tempy / 3686400.0;
        $point = new Point();
        $point->setX( $tempx );
        $point->setY( $tempy );
        return $point;
    }

	// WGS -> BaiDu
    public function WGStoBaiDuPoint( $x, $y ) {
        $x1 = $x * 3686400.0;
        $y1 = $y * 3686400.0;
	$x_pi = 3.14159265358979324 * 3000.0 / 180.0;
        $gpsWeek = 0;
        $gpsWeekTime = 0;
        $gpsHeight = 0;
        $point = $this->wgtochina_lb( 1, (int)$x1, (int)$y1, (int)$gpsHeight, (int)$gpsWeek, (int)$gpsWeekTime );
	$x = $point->getX()/3686400.0;
        $y = $point->getY()/3686400.0;
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);
        $point->setX($z * cos($theta) + 0.0065);
        $point->setY($z * sin($theta) + 0.006);
        return $point;
    }

    public function ChinatoBaiDuPoint( $x, $y ) {
	$x_pi = 3.14159265358979324 * 3000.0 / 180.0;
        $point = new Point();
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);
        $point->setX($z * cos($theta) + 0.0065);
        $point->setY($z * sin($theta) + 0.006);
        return $point;
    }

    protected function yj_sin2( $x ) {
        $tt;
        $ss;
        $ff;
        $s2;
        $cc;
        $ff = 0;
        if ( $x < 0 ) {
            $x = -$x;
            $ff = 1;
        }

        $cc = (int)($x / 6.28318530717959);

        $tt = $x - $cc * 6.28318530717959;
        if ( $tt > 3.1415926535897932 ) {
            $tt = $tt - 3.1415926535897932;
            if ( $ff == 1 ) {
                $ff = 0;
            }
            else if ( $ff == 0 ) {
                $ff = 1;
            }
        }
        $x = $tt;
        $ss = $x;
        $s2 = $x;
        $tt = $tt * $tt;
        $s2 = $s2 * $tt;
        $ss = $ss - $s2 * 0.166666666666667;
        $s2 = $s2 * $tt;
        $ss = $ss + $s2 * 8.33333333333333E-03;
        $s2 = $s2 * $tt;
        $ss = $ss - $s2 * 1.98412698412698E-04;
        $s2 = $s2 * $tt;
        $ss = $ss + $s2 * 2.75573192239859E-06;
        $s2 = $s2 * $tt;
        $ss = $ss - $s2 * 2.50521083854417E-08;
        if ( $ff == 1 ) {
            $ss = -$ss;
        }
        return $ss;
    }

    protected function Transform_yj5( $x, $y ) {
        $tt;
        $tt = 300 + 1 * $x + 2 * $y + 0.1 * $x * $x + 0.1 * $x * $y + 0.1 * sqrt( sqrt( $x * $x ) );
        $tt = $tt + ( 20 * $this->yj_sin2( 18.849555921538764 * $x ) + 20 * $this->yj_sin2( 6.283185307179588 * $x ) ) * 0.6667;
        $tt = $tt + ( 20 * $this->yj_sin2( 3.141592653589794 * $x ) + 40 * $this->yj_sin2( 1.047197551196598 * $x ) ) * 0.6667;
        $tt = $tt + ( 150 * $this->yj_sin2( 0.2617993877991495 * $x ) + 300 * $this->yj_sin2( 0.1047197551196598 * $x ) ) * 0.6667;
        return $tt;
    }

    protected function Transform_yjy5( $x, $y ) {
        $tt;
        $tt = -100 + 2 * $x + 3 * $y + 0.2 * $y * $y + 0.1 * $x * $y + 0.2 * sqrt( sqrt( $x * $x ) );
        $tt = $tt + ( 20 * $this->yj_sin2( 18.849555921538764 * $x ) + 20 * $this->yj_sin2( 6.283185307179588 * $x ) ) * 0.6667;
        $tt = $tt + ( 20 * $this->yj_sin2( 3.141592653589794 * $y ) + 40 * $this->yj_sin2( 1.047197551196598 * $y ) ) * 0.6667;
        $tt = $tt + ( 160 * $this->yj_sin2( 0.2617993877991495 * $y ) + 320 * $this->yj_sin2( 0.1047197551196598 * $y ) ) * 0.6667;
        return $tt;
    }

    protected function Transform_jy5( $x, $xx ) {
        $n;
        $a;
        $e;
        $a = 6378245;
        $e = 0.00669342;
        $n = sqrt( 1 - $e * $this->yj_sin2( $x * 0.0174532925199433 ) * $this->yj_sin2( $x * 0.0174532925199433 ) );
        $n = ( $xx * 180) / ( $a / $n * cos( $x * 0.0174532925199433 ) * 3.1415926 );
        return $n;
    }

    protected function Transform_jyj5( $x, $yy ) {
        $m;
        $a;
        $e;
        $mm;
        $a = 6378245;
        $e = 0.00669342;
        $mm = 1 - $e * $this->yj_sin2( $x * 0.0174532925199433) * $this->yj_sin2( $x * 0.0174532925199433 );
        $m = ( $a * ( 1 - $e ) ) / ( $mm * sqrt( $mm ) );
        return ( $yy * 180 ) / ( $m * 3.1415926 );
    }


    protected function r_yj() {
        $casm_a = 314159269;
        $casm_c = 453806245;
        return 0;
    }

    protected function random_yj() {
        $t;
        $casm_a = 314159269;
        $casm_c = 453806245;
        $this->casm_rr = $casm_a * $this->casm_rr + $casm_c;
        $t = (int)( $this->casm_rr / 2 );
        $this->casm_rr = $this->casm_rr - $t * 2;
        $this->casm_rr = $this->casm_rr / 2;
        return ( $this->casm_rr );
    }

    protected function IniCasm( $w_time, $w_lng, $w_lat ) {
        $tt;
        $this->casm_t1 = $w_time;
        $this->casm_t2 = $w_time;
        $tt = (int)( $w_time / 0.357 );
        $this->casm_rr = $w_time - $tt * 0.357;
        if ( $w_time == 0 ) 
            $this->casm_rr = 0.3;
        $this->casm_x1 = $w_lng;
        $this->casm_y1 = $w_lat;
        $this->casm_x2 = $w_lng;
        $this->casm_y2 = $w_lat;
        $this->casm_f = 3;
    }

    protected function wgtochina_lb( $wg_flag, $wg_lng, $wg_lat, $wg_heit, $wg_week, $wg_time ) {
        $x_add;
        $y_add;
        $h_add;
        $x_l;
        $y_l;
        $casm_v;
        $t1_t2;
        $x1_x2;
        $y1_y2;
        $point = new Point();
        $point->setX( $wg_lng );
        $point->setY( $wg_lat );
        if ( $wg_heit > 5000 ) {
            return $point;
        }
        $x_l = $wg_lng;
        $x_l = $x_l / 3686400.0;
        $y_l = $wg_lat;
        $y_l = $y_l / 3686400.0;
        if ( $x_l < 72.004 ) {
            return $point;
        }
        if ( $x_l > 137.8347) {
            return $point;
        }
        if ( $y_l < 0.8293 ) {
            return $point;
        }
        if ( $y_l > 55.8271 ) {
            return $point;
        }
        if ( $wg_flag == 0 ) {
            $this->IniCasm( $wg_time, $wg_lng, $wg_lat );
            $point = new Point();
            $point->setLatitude( $wg_lng );
            $point->setLongitude( $wg_lat );
            return $point;
        }
        $this->casm_t2 = $wg_time;
        $t1_t2 = ( $this->casm_t2 - $this->casm_t1 ) / 1000.0;
        if ( $t1_t2 <= 0 ) {
            $this->casm_t1 = $this->casm_t2;
            $this->casm_f = $this->casm_f + 1;
            $this->casm_x1 = $this->casm_x2;
            $this->casm_f = $this->casm_f + 1;
            $this->casm_y1 = $this->casm_y2;
            $this->casm_f = $this->casm_f + 1;
        }
        else {
            if ( $t1_t2 > 120 ) 
            {
                if ($this->casm_f == 3) 
                {
                    $this->casm_f = 0;
                    $this->casm_x2 = $wg_lng;
                    $this->casm_y2 = $wg_lat;
                    $x1_x2 = $this->casm_x2 - $this->casm_x1;
                    $y1_y2 = $this->casm_y2 - $this->casm_y1;
                    $casm_v = sqrt( $x1_x2 * $x1_x2 + $y1_y2 * $y1_y2 ) / t1_t2;
                    if ( $casm_v > 3185 ) 
                    {
                        return $point;
                    }
                }
                $this->casm_t1 = $this->casm_t2;
                $this->casm_f = $this->casm_f + 1;
                $this->casm_x1 = $this->casm_x2;
                $this->casm_f = $this->casm_f + 1;
                $this->casm_y1 = $this->casm_y2;
                $this->casm_f = $this->casm_f + 1;
            }
        }
        $x_add = $this->Transform_yj5( $x_l - 105, $y_l - 35 );
        $y_add = $this->Transform_yjy5( $x_l - 105, $y_l - 35 );
        $h_add = $wg_heit;
        $x_add = $x_add + $h_add * 0.001 + $this->yj_sin2( $wg_time * 0.0174532925199433 ) + $this->random_yj();
        $y_add = $y_add + $h_add * 0.001 + $this->yj_sin2( $wg_time * 0.0174532925199433 ) + $this->random_yj();
        $point = new Point();
        $point->setX( ( ( $x_l + $this->Transform_jy5( $y_l, $x_add ) ) * 3686400 ) );
        $point->setY( ( ( $y_l + $this->Transform_jyj5( $y_l, $y_add ) ) * 3686400 ) );
        return $point;
    }


    protected function isValid( $validdays ) {
        //long standand = 1253525356;
        $h = 3600;
        $currentTime = new DateTime();
        if( $currentTime.getTimestamp() / 1000 - 1253525356 >= $validdays * 24 * $h ) {
            return false;
        }
        else {
            return true;
        }
    }

    protected function getEncryCoord( $coord, $flag ) {
        if ( $flag )  {
            $xy = $coord.split(",");
            $x = (Double)($xy[0]);
            $y = (Double)($xy[1]);
            $point = new Point();
            $x1;
            $tempx;
            $y1;
            $tempy;
            $x1 = $x * 3686400.0;
            $y1 = $y * 3686400.0;
            $gpsWeek = 0;
            $gpsWeekTime = 0;
            $gpsHeight = 0;
            $point = $this->wgtochina_lb( 1, (int)$x1, (int)$y1, (int)$gpsHeight, (int)$gpsWeek, (int)$gpsWeekTime );
            $tempx = $point->getX();
            $tempy = $point->getY();
            $tempx = $tempx / 3686400.0;
            $tempy = $tempy / 3686400.0;
            return $tempx + "," + $tempy;
        }
        else 
        {
            return "";
        }
    }
}

// $mapPoint = new Converter();
// print_r( $mapPoint->getBDEncryPoint( 114.27990893365145, 30.604389662298505 ) );

?>
