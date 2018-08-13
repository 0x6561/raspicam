<?php
// $GLOBALS
$base_path = $_SERVER['DOCUMENT_ROOT'];
$img_path = dirname($_SERVER['SCRIPT_NAME']) . '/photos/';
$cur_img = $img_path . 'default.jpg';
$dashboard = NULL;

$exposures = ["off", "auto", "night",
  "nightpreview", "backlight",
  "spotlight", "sports", "snow",
  "beach", "verylong", "fixedfps",
  "antishake", "fireworks"];

$img_effect =["none", "negative", 
  "solarise", "sketch", "denoise", 
  "emboss", "oilpaint", "hatch", 
  "gpen", "pastel", "watercolour", 
  "film", "blur", "saturation", 
  "colourswap", "washedout", 
  "posterise", "colourpoint", 
  "colourbalance", "cartoon "];

function set_debug($debug){
  if($debug){
    ini_set('display_errors', 'On');
    error_reporting(E_ALL | E_STRICT);
  }
}

// chekc for a request (GET)
function rest_get($param){
  $get_param_val = NULL;
  if(!empty($_GET) &&
    isset($_GET[$param]) &&
    !empty($_GET[$param])){
    $get_param_val =  filter_var($_GET[$param], FILTER_SANITIZE_FULL_SPECIAL_CHARS);//crosses fingers...
  }
  return $get_param_val;
}

function add_menu(){
  echo '<form action="raspicam.php">';

  echo '<ul class="columns">';

  echo '<li><label for="exposure">Exposure</label>';
  echo '<select name="exposure" id="exposure">';
  foreach($GLOBALS['exposures'] as $opt){
    echo '<option value="' . $opt . '">' . $opt . '</option>';
  }
  echo '</select></li>';

  echo '<li><label for="img_effect">Effects</label>';
  echo '<select name="img_effect" id="img_effect">';
  foreach($GLOBALS['img_effect'] as $opt){
    echo '<option value="' . $opt . '">' . $opt . '</option>';
  }
  echo '</select></li>';

  echo '<li><label for="sharpness">Sharpness</label>';
  echo '<select name="sharpness" id="sharpness">';
  for($opt = -100; $opt<101; $opt++){
    if($opt == 50){
      echo '<option value="' . $opt . '" selected>' . $opt . '</option>';
    }else{
      echo '<option value="' . $opt . '">' . $opt . '</option>';
    }
  }
  echo '</select></li>';

  echo '<li><label for="contrast">Contrast</label>';
  echo '<select name="contrast" id="contrast">';
  for($opt = -100; $opt<101; $opt++){
    if($opt == 0){
      echo '<option value="' . $opt . '" selected>' . $opt . '</option>';
    }else{
      echo '<option value="' . $opt . '">' . $opt . '</option>';
    }
  }
  echo '</select></li>';

  echo '<li><label for="brightness">Brightness</label>';
  echo '<select name="brightness" id="brightness">';
  for($opt = -100; $opt<101; $opt++){
    if($opt == 20){
      echo '<option value="' . $opt . '" selected>' . $opt . '</option>';
    }else{
      echo '<option value="' . $opt . '">' . $opt . '</option>';
    }
  }
  echo '</select></li>';

  echo '<li><label for="saturation">Saturation</label>';
  echo '<select name="saturation" id="saturation">';
  for($opt = -100; $opt<101; $opt++){
    if($opt == 20){
      echo '<option value="' . $opt . '" selected>' . $opt . '</option>';
    }else{
      echo '<option value="' . $opt . '">' . $opt . '</option>';
    }
  }
  echo '</select></li>';

  echo '</ul><input type="submit" value="Take Photo"></form>';
}

function set_dashboard($disk_used){
  if($disk_used < 55){
    $color = 'green';
  }else if($disk_used < 65){
    $color = 'yellow';
  }else if($disk_used < 75){
    $color = 'orange';
  }else{
    $color = 'red';
  }

  $disk_left = 100 - $disk_used;

  $dash = '<h3>disk usage</h3><table style=" border: 1% solid white; height: 15%; width: 100%; cellspacing: 0;"><tbody><tr>';
  $dash = $dash . '<td style=" height: 100%; width: ' . $disk_used . '%; color: black; background-color: ' . $color . ';">'. $disk_used.'% USED</td>';
  $dash = $dash . '<td style=" height: 100%; width: 100%; border: 1% solid ' . $color . '; color: black; background-color: white;">';
  $dash = $dash . $disk_left .'% AVAILABLE</td></tr></tbody></table>';
  $GLOBALS['dashboard'] = $dash;
}

//-ex, --exposure	: Set exposure mode (see Notes)
//-ifx, --imxfx	: Set image effect (see Notes)
//-sh, --sharpness	: Set image sharpness (-100 to 100)
//-co, --contrast	: Set image contrast (-100 to 100)
//-br, --brightness	: Set image brightness (0 to 100)
//-sa, --saturation	: Set image saturation (-100 to 100)
function get_options(){
  $options = NULL;
  $ex = rest_get('exposure');
  $ifx = rest_get('img_effect');
  $sharpness = rest_get('sharpness');
  $contrast = rest_get('contrast');
  $brightness = rest_get('brightness');
  $saturation = rest_get('saturation');

  if(($ex != NULL) && (in_array($ex, $GLOBALS['exposures']))){
    $options = $options . ' -ex ' . $ex; 
  }
  if(($ifx != NULL) && (in_array($ifx, $GLOBALS['img_effect']))){
    $options = $options .  ' -ifx ' . $ifx; 
  }
  if(($sharpness != NULL) && ($sharpness > -101) && ($sharpness < 101)){
    $options = $options .  ' -sh ' . $sharpness; 
  }
  if(($contrast != NULL) && ($contrast > -101) && ($contrast < 101)){
    $options = $options .  ' -co ' . $contrast; 
  }
  if(($brightness != NULL) && ($brightness > -1) && ($brightness < 101)){
    $options = $options .  ' -br ' . $brightness; 
  }
  if(($saturation != NULL) && ($saturation > -1) && ($saturation < 101)){
    $options = $options .  ' -sa ' . $saturation; 
  }
  return $options;
}

//return %disk used
function check_disk_space(){
  $output = NULL; 
  $return_var = NULL; 
  $cmd = '/bin/df -h | /usr/bin/head -n 2 | /usr/bin/tail -1 | /usr/bin/awk {\'print $5\'}';
  exec($cmd, $output, $return_var);
  return str_replace('%', '', $output[0]);
}

function take_pic(){
  $output = NULL; 
  $return_var = NULL; 
  $filename = 'raspicam_' . implode("_", getdate()) . '.jpg'; 
  $options = get_options();
  $cmd = '';
  if($options != NULL){
    $cmd = '/usr/bin/raspistill -t 1 -v ' . $options . ' -o '. $GLOBALS['base_path'] . $GLOBALS['img_path'] . $filename;
  }else{
    $cmd = '/usr/bin/raspistill -t 1 -v ' . ' -o ' . $GLOBALS['base_path'] . $GLOBALS['img_path'] . $filename;
  }
  exec($cmd, $output, $return_var);
  if($return_var == 0){// && (file_exists(__DIR__ . $GLOBALS['img_path'] . $filename))){
    $GLOBALS['cur_img'] = $GLOBALS['img_path'] . $filename;
  }
}

set_debug(true);

$disk_used = check_disk_space();
set_dashboard($disk_used);
if($disk_used < 95){
  take_pic();
}
?>
<!DOCTYPE html>
<html>
  <meta charset="utf-8">
  <title>Raspicam</title>
  <head>
    <link rel="stylesheet" type="text/css" href="style.css">
    <script src="js/jquery-3.3.1.min.js"></script>
  </head>
  <body><h1><a href="/php/code/php/raspicam/raspicam.php">Raspicam</a></h1>
<div>
<?php add_menu(); ?>
</div>
<div>
<img height="75%" width="75%" src="<?php echo $cur_img; ?>" alt="there should be an image here...">
</div>
<div><?php echo $dashboard; ?></div>
</body>
</html>

<?php
/*
Image parameter commands

-w, --width	: Set image width <size>
-h, --height	: Set image height <size>
-q, --quality	: Set jpeg quality <0 to 100>
-o, --output	: Output filename <filename> (to write to stdout, use '-o -'). If not specified, no file is saved
-v, --verbose	: Output verbose information during run
-t, --timeout	: Time (in ms) before takes picture and shuts down (if not specified, set to 5s)
-e, --encoding	: Encoding to use for output file (jpg, bmp, gif, png)
-x, --exif	: EXIF tag to apply to captures (format as 'key=value') or none
-tl, --timelapse	: Timelapse mode. Takes a picture every <t>ms. %d == frame number (Try: -o img_%04d.jpg)
-s, --signal	: Wait between captures for a SIGUSR1 or SIGUSR2 from another process
-set, --settings	: Retrieve camera settings and write to stdout
-bm, --burst	: Enable 'burst capture mode'
-md, --mode	: Force sensor mode. 0=auto. See docs for other modes available
-dt, --datetime	: Replace output pattern (%d) with DateTime (MonthDayHourMinSec)

Image parameter commands

-sh, --sharpness	: Set image sharpness (-100 to 100)
-co, --contrast	: Set image contrast (-100 to 100)
-br, --brightness	: Set image brightness (0 to 100)
-sa, --saturation	: Set image saturation (-100 to 100)
-ISO, --ISO	: Set capture ISO
-vs, --vstab	: Turn on video stabilisation
-ev, --ev	: Set EV compensation - steps of 1/6 stop
-ex, --exposure	: Set exposure mode (see Notes)
-awb, --awb	: Set AWB mode (see Notes)
-ifx, --imxfx	: Set image effect (see Notes)
-cfx, --colfx	: Set colour effect (U:V)
-mm, --metering	: Set metering mode (see Notes)
-rot, --rotation	: Set image rotation (0-359)
-hf, --hflip	: Set horizontal flip
-vf, --vflip	: Set vertical flip
-roi, --roi	: Set region of interest (x,y,w,d as normalised coordinates [0.0-1.0])
-ss, --shutter	: Set shutter speed in microseconds
-awbg, --awbgains	: Set AWB gains - AWB mode must be off
-drc, --drc	: Set DRC Level (see Notes)
-st, --stats	: Force recomputation of statistics on stills capture pass

Notes

Exposure mode options :
off,auto,night,nightpreview,backlight,spotlight,sports,snow,beach,verylong,fixedfps,antishake,fireworks

AWB mode options :
off,auto,sun,cloud,shade,tungsten,fluorescent,incandescent,flash,horizon

Image Effect mode options :
none,negative,solarise,sketch,denoise,emboss,oilpaint,hatch,gpen,pastel,watercolour,film,blur,saturation,colourswap,washedout,posterise,colourpoint,colourbalance,cartoon

Metering Mode options :
average,spot,backlit,matrix

Dynamic Range Compression (DRC) options :
off,low,med,high
 */
?>
