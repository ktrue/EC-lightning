<?php
// ------------settings -----------------------------------
// this script should be in the same directory as ec-lightning.php with
// $lightningDir value the same as in ec-lightning.php 
//
$lightningDir = './radar/'; // directory for storing lightning-XXX-0.png to lightning-XXX-6.png images
//                                  note: use relative addressing to current directory
//                                  default = './radar/' to match ec-radar.php script.
//-------------end of settings-------------------------------

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   readfile($filenameReal);
   exit;
}
error_reporting(E_ALL);  // uncomment to turn on full error reporting
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>PHP file writing test for ec-lightning.php</title>
<style type="text/css">
body {
  background-color:#FFFFFF;
  font-family:Verdana, Arial, Helvetica, sans-serif;
  font-size: 12px;
}
</style>
</head>
<h1>Test for ec-lightning.php file caching and GD image function</h1>
<?php
//
// constants
//
    $cacheName = 'ec-lightning-test.txt';
    $siteID = 'TST';
    $Lang = 'en';
// run the tests
//
$cacheName = preg_replace('|.txt$|',"-$Lang.txt",$cacheName);

// 

$imageDir = $lightningDir;

$RealCacheName = $lightningDir  . $cacheName;
	
	$NOWgmt = time();
    $NOWdate = gmdate("D, d M Y H:i:s", $NOWgmt);
	echo "<p>Using <strong>$RealCacheName</strong> as test file.</p>\n";
	echo "<p>Now date='$NOWdate'</p>\n";
	$fp = fopen($RealCacheName,"w");
	if ($fp) {
	  $rc = fwrite($fp,$NOWdate);
	  if ($rc <> strlen($NOWdate)) {
	    echo "<p>unable to write $RealCacheName: rc=$rc</p>\n";
	  }
	  fclose($fp);
	} else {
	  echo "<p>Unable to open $RealCacheName for write.</p>\n";
	}
	
	$contents = implode('',file($RealCacheName));
	
	echo "<p>File says='$contents'</p>\n";
	if ($contents == $NOWdate) {
	  echo "<p>Write and read-back successful.. contents identical -- ec-lightning.php cache should work fine with <strong>\$lightningDir = '$lightningDir';</strong> setting.</p>\n";
	} else {
	  echo "<p>Read-back unsuccessful. contents different -- ec-lightning.php cache will not work correctly</p>\n";
	}
	
?>
<h2>Checking for legendLightning.png required image file</h2>
<?php if(!file_exists($lightningDir.'legendLightning.png')) { ?>
<p><strong>MISSING file:</strong> Right-click on this image <img src="//saratoga-weather.org/radar/legendLightning.png" alt="lightning legend" /> and <em>Save Image As...</em>to filename <strong>legendLightning.png</strong> in the <strong><?php echo $lightningDir; ?></strong> directory on your website.<br/>After that is done, rerun this program to check again.</p>
<?php } else { ?>
<p>Your legendLightning.png is correctly placed in <?php echo $lightningDir; ?> and shows like this: 
<img src="<?php echo $lightningDir.'legendLightning.png'; ?>" alt="lightning legend" /></p>
<?php } // end check for lightning legend image ?>
<?php echo "<h2>Site is running PHP Version " . phpversion() ."</h2>"; ?> 
<p>To run the ec-lightning.php script, you also need GD enabled in PHP.<br />
Make sure the following displays Yes for all items (except for 'WebP Support', 'T1Lib Support' and 'JIS-mapped Japanese Font Support' which are not needed).</p>
<h2>Current GD status:</h2>
<?php  echo describeGDdyn();
  
// Retrieve information about the currently installed GD library
// script by phpnet at furp dot com (08-Dec-2004 06:59)
//   from the PHP usernotes about gd_info
function describeGDdyn() {
 echo "\n<ul><li>GD support: ";
 if(function_exists("gd_info")){
  echo "<span style=\"color: #00ff00\"><b>YES</b></span>";
  $info = gd_info();
  $keys = array_keys($info);
  for($i=0; $i<count($keys); $i++) {
if(is_bool($info[$keys[$i]])) echo "</li>\n<li>" . $keys[$i] .": " . yesNo($info[$keys[$i]]);
else echo "</li>\n<li>" . $keys[$i] .": " . $info[$keys[$i]];
  }
 } else { echo "<span style=\"color: #ff0000\"><b>NO</b></span>"; }
 echo "</li></ul>";
}
function yesNo($bool){
 if($bool) return "<span style=\"color: #00ff00\"><b> YES</b></span>";
 else return "<span style=\"color: #ff0000\"><b> NO</b></span>";
}


?>

<body>
</body>
</html>