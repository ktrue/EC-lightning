<?php
// PHP script by Ken True, webmaster@saratoga-weather.org
// ec-lightning.php  version 1.00 - 14-Oct-2016
// Version 1.00 - 15-Oct-2016 - initial release based on ec-radar.php V2.01 and a lot of rewrite
// Version 1.01 - 13-Oct-2017 - use curl for fetch, use HTTPS to EC website
// Version 1.02 - 15-Oct-2017 - corrected undefined function error now() -> time()
// Version 1.03 - 18-Jan-2022 - fix for extract of source HTML Charset to use
// Version 1.04 - 27-Dec-2022 - fixes for PHP 8.1
//
  $Version = "V1.04 - 27-Dec-2022";
//
// Settings:
// --------- start of settings ----------
//
//  Go to http://weather.gc.ca/lightning/index_e.html
//  Click on the desired area page.
//  You should see a lightning page with an url like
//     http://weather.gc.ca/lightning/index_e.html?id=XXX
//  copy the three letter area id=XXX into $lightningID = 'XXX'; below
//
$lightningID = 'NAT';      // set to default Site for lightning (same as id=xxx on EC website)
//                         // available sites: NAT ARC PAC WRN ONT QUE ATL
$defaultLang = 'en';  // set to 'fr' for french default language
//                    // set to 'en' for english default language
//
$lightningCacheName = 'ec-lightning.txt';     // note: will be changed to -en.txt or 
//                                  -fr.txt depending on language choice and stored in $lightningDir
$lightningDir = './radar/';  // directory for storing lightning-XXX-0.png to lightning-XXX-6.png images
//                             note: relative to document root.
$lightningWidth = 620;  // width of images to output in pixels.  default=620
//
$refetchSeconds = 300;  // look for new images from EC every 5 minutes (300 seconds)
//                      NOTE: EC may take up to 20 minutes to publish new images    
$noLightningMinutes = 25;   // minutes to wait before declaring the image site as 'N/O -not operational'
//
$aniSec = 1; // number of seconds between animations
//
$charsetOutput = 'ISO-8859-1';   // default character encoding of output
// ---------- end of settings -----------
//------------------------------------------------
// overrides from Settings.php if available
global $SITE;
if (isset($SITE['eclightningID'])) 	{$lightningID = $SITE['eclightningID'];}
if (isset($SITE['defaultlang'])) 	{$defaultLang = $SITE['defaultlang'];}
if (isset($SITE['charset']))	{$charsetOutput = strtoupper($SITE['charset']); }
// end of overrides from Settings.php if available
//
// ---------- main code -----------------
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain,charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   readfile($filenameReal);
   exit;
}
//error_reporting(E_ALL);  // uncomment to turn on full error reporting

$hasUrlFopenSet = ini_get('allow_url_fopen');
if(!$hasUrlFopenSet) {
	print "<h2>Warning: PHP does not have 'allow_url_fopen = on;' --<br/>image fetch by ec-lightning.php is not possible.</h2>\n";
	print "<p>To fix, add the statement: <pre>allow_url_fopen = on;\n\n</pre>to your php.ini file to enable ec-lightning.php operation.</p>\n";
	return;
}
$t = pathinfo(__FILE__);  // get our program name for the HTML comments
$Program = $t['basename'];
$Status = "<!-- ec-lightning.php - $Version -->\n";

$printIt = true;
if(isset($_REQUEST['inc']) && strtolower($_REQUEST['inc']) == 'y' or 
  (isset($doInclude) and $doInclude)) {$doInclude = true;}
if(isset($doPrint)) { $printIt = $doPrint; }
if(! isset($doInclude)) {$doInclude = false; }

if(isset($_REQUEST['id'])) { $lightningID = strtoupper($_REQUEST['id']); }
$lightningID = preg_replace('|[^A-Z]+|s','',$lightningID); // Make sure only alpha in siteID
if(strlen($lightningID) <> 3) {
	print "<p>Sorry... area id '$lightningID' is not a valid EC area site name.</p>\n";
	return;
}
if (isset($_REQUEST['cache']) && (strtolower($_REQUEST['cache']) == 'no') ) {
  $forceRefresh = true;
} else {
  $forceRefresh = false;
}

if (isset($doAutoPlay)) {
	$autoPlay = $doAutoPlay;
} elseif (isset($_REQUEST['play']) && (strtolower($_REQUEST['play']) == 'no') ) {
  $autoPlay = false;
} else {
  $autoPlay = true;
}

if (isset($_REQUEST['imgonly']) && (strtolower($_REQUEST['imgonly']) == 'y')) {
  $imageOnly = true;  // just return the latest thumbnail image after processing
  $printIt = false;   // and don't spoil the image with any other stuff
} else {
  $imageOnly = false;
}

if (isset($_REQUEST['lang'])) {
$Lang = strtolower($_REQUEST['lang']);
}
if (isset($doLang)) {$Lang = $doLang;};
if (! isset($Lang)) {$Lang = $defaultLang;};

if ($Lang == 'fr') {
  $LMode = 'f';
  $ECLNAME = "Environnement Canada";
  $ECLHEAD = 'Carte canadienne du risque de foudre';
  $ECLNO = 'N/O - Non opérationnel';
  $ECLNoJS = 'Pour voir l\'animation, il faut que JavaScript soit en fonction.';
  $ECLPlay = 'Animer - Pause';
  $ECLPrev = 'Image pr&#233;c&#233;dente';
  $ECLNext = 'Prochaine image';
} else {
  $Lang = 'en';
  $LMode = 'e';
  $ECLNAME = "Environment Canada";
  $ECLHEAD = 'Canadian Lightning Danger Map';
  $ECLNO = 'N/O - Non-operational';
  $ECLNoJS = 'Please enable JavaScript to view the animation.';
  $ECLPlay = 'Play - Stop';
  $ECLPrev = 'Previous';
  $ECLNext = 'Next';
}
$lightningCacheName = preg_replace('|.txt$|',"-$Lang.txt",$lightningCacheName);

// 
if (isset($_SERVER['DOCUMENT_ROOT'])) {
  $ROOTDIR = $_SERVER['DOCUMENT_ROOT']; 
} else { 
  $ROOTDIR = '.';
}

$cacheDir = $lightningDir;
$imageDir = $lightningDir;

$Status .= "<!-- cacheDir='$cacheDir' -->\n<!-- imageDir='$imageDir' -->\n";
date_default_timezone_set( @date_default_timezone_get());
$Status .= "<!-- date default timezone='".date_default_timezone_get()."' -->\n";
$ECLSizes = array ( // image sizes w,h
  'NAT' => '850,633',
  'ARC' => '850,646',
  'PAC' => '667,700',
  'WRN' => '795,700',
  'ONT' => '764,700',
  'QUE' => '622,700',
  'ATL' => '792,700',
);

if ( !isset($ECLSizes[$lightningID]) ) {
	print "<h2>Error: id=$lightningID is not known. </h2>\n";
	print "<p> Use one of the following as id: ";
	foreach ($ECLSizes as $key => $val) { print "$key "; }
	print " and retry.</p>\n";
	return;
}

list($ECLbaseW,$ECLbaseH) = explode(',',$ECLSizes[$lightningID]);


$new_width = $lightningWidth;
$new_height = round($ECLbaseH * ($lightningWidth / $ECLbaseW),0);
	

$thumb_width = 290;
$thumb_height = round($ECLbaseH * (290 / $ECLbaseW),0);

  
//  all settings and overrides now loaded ... begin processing

$Status .= "<!-- id=$lightningID raw-image w,h=".$ECLSizes[$lightningID] .
    " scale to w,h=$new_width,$new_height thumb w,h=$thumb_width,$thumb_height -->\n";

$lightningCacheName = preg_replace('|.txt$|',"-".$lightningID.".txt",$lightningCacheName);
$RawImgURL = "https://weather.gc.ca";

$ECLURL = 'https://weather.gc.ca/lightning/index_' . $LMode . '.html?id=' . $lightningID;

if($Lang == 'fr') {
	$RawImgURL = preg_replace('|weather|i','meteo',$RawImgURL);
	$ECLURL     = preg_replace('|weather|i','meteo',$ECLURL);
	$Status .= "<!-- french language - using meteo.gc.ca for data -->\n";
}

$RealCacheName = $cacheDir  . $lightningCacheName;

$reloadImages = false;  // assume we don't have to reload unless a newer image set is around

if(file_exists($RealCacheName)) {
	$lastCacheTime = filemtime($RealCacheName);
} else {
	$lastCacheTime = time();
	$forceRefresh = true;
}

$lastCacheTimeHM = gmdate("Y-m-d H:i:s",$lastCacheTime) . " UTC";
$NOWgmtHM        = gmdate("Y-m-d H:i:s",time()) . " UTC";
$diffSecs = time() - $lastCacheTime; 
$Status .= "<!-- now='$NOWgmtHM' page cached='$lastCacheTimeHM' ($diffSecs seconds ago) -->\n";	
if(isset($_GET['force']) | isset($_GET['cache'])) {$refetchSeconds = 0;}

if($diffSecs > $refetchSeconds) {$forceRefresh = true;}

$Status .= "<!-- forceRefresh=";
$Status .= $forceRefresh?'true':'false';
$Status .= " -->\n";
// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org
if (! $forceRefresh) {
      $Status .= "<!-- using Cached version from $lightningCacheName -->\n";
      $site = implode('', file($RealCacheName));
	  $forceRefresh = true;
    } else {
      $Status .= "<!-- loading $lightningCacheName from\n  '$ECLURL' -->\n";
      $site = ECL_fetchUrlWithoutHang($ECLURL,false);
      $fp = fopen($RealCacheName, "w");
	  if (strlen($site) and $fp) {
        $write = fputs($fp, $site);
        fclose($fp);  
        $Status .= "<!-- loading finished. New page cache saved to $lightningCacheName ".strlen($site)." bytes -->\n";
		$reloadImages = true;
	  } else {
        $Status .= "<!-- unable to open $lightningCacheName for writing ".strlen($site)." bytes.. cache not saved -->\n";
		$Status .= "<!-- file: '$RealCacheName' -->\n";
		$Status .= "<!-- html loading finished -->\n";
	  }
}
  if(!file_exists($RealCacheName)) {
	  print "<p>Sorry.  Unable to write $lightningCacheName to '$cacheDir'.<br/>";
	  print "Make '$cacheDir' writable by PHP for this script to operate properly.</p>\n";
	  exit;
  }
  
  if(strlen($site) < 100) {
	  print "<p>Sorry. Incomplete file received from Environment Canada website.</p>\n";
	  exit;
  }
  preg_match('|charset="{0,1}([^"]+)"{0,1}\r|i',$site,$matches);
  
  if (isset($matches[1])) {
    $charsetInput = strtoupper($matches[1]);
  } else {
    $charsetInput = 'UTF-8';
  }
  
 $doIconv = ($charsetInput == $charsetOutput)?false:true; // only do iconv() if sets are different
 
 $Status .= "<!-- using charsetInput='$charsetInput' charsetOutput='$charsetOutput' doIconv='$doIconv' -->\n";

// find the site name
//

   preg_match_all('|<title>(.*)</title>|',$site,$matches);
//   $Status .= "<!-- title matches\n" . print_r($matches,true) . " -->\n";
   
   $siteTitle = isset($matches[1][0])?$matches[1][0]:'';
   if($doIconv and $siteTitle) { 
     $siteTitle = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteTitle);
   }
   
// find the site heading info
   preg_match_all('|<h1 id="wb-cont" property="name">(.*)</h1>|',$site,$matches);
//   $Status .= "<!-- name matches\n" . print_r($matches,true) . " -->\n";
   $siteHeading = isset($matches[1][0])?$matches[1][0]:'';
   if($doIconv and $siteHeading) { 
     $siteHeading = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteHeading);
   }

  
// find the legend info and build our version.

   $ECLlegend = '';
   preg_match('|<div class="col-xs-1 (.*)</ul>|Uis',$site,$matches);
   
   if(isset($matches[1])) {
	   $tstr = $matches[1];
	   preg_match_all('|<li>([^<]+)</li>|Uis',$tstr,$matches);
       // $Status .= "<!-- matches\n" . print_r($matches,true) . " -->\n";
	   $ECLlegend = 
	   "<table class=\"EClightning\" style=\"border: 1px solid black; margin: 1em auto;\">\n" .
	   "<tr><td width=\"55px\" style=\"text-align: right;\">\n".
	   "<img src=\"{$lightningDir}legendLightning.png\" alt=\"red dot\" width=\"50\" height=\"50\"/>\n".
	   "</td>\n";
	   $ECLlegend .= "<td style=\"text-align: left\">\n<ul>\n";
	   foreach ($matches[1] as $i => $val) {
         if($doIconv) { 
           $tstr = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$val);
         } else {
		   $tstr = $val;
		 }
		 $ECLlegend .= "<li>$tstr</li>\n";
	   }
	   $ECLlegend .= "</ul>\n</td></tr>\n</table>\n";
	   
	   $Status .= "<!-- ECLlegend extracted -->\n";
	   //print $ECLlegend;
	   
   }

// find the string to use for 'enable JavaScript for the animation'
   
   if(preg_match_all('|<canvas [^>]+>(.*)</canvas|',$site,$matches) ) {
     $noJSMsg = $matches[1][0];
   } else {
     $noJSMsg = '';
   }
   if($doIconv and $noJSMsg) { 
     $noJSMsg = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$noJSMsg);
   }
   $Status .= "<!-- noscript='$noJSMsg' -->\n";
   
// find the site description info

   $siteDescription = '';
   if(preg_match_all('|<p class="hidden-xs">(.*)</p>|Uis',$site,$matches) ) {
	 $siteDescription = $matches[1][0];
	 if($doIconv and $siteDescription) {
	   $siteDescription = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteDescription);
	 }
   }
   
// find and extract the details about the images available
   preg_match('|<div id="wxo-animator" ([^>]+)>|',$site,$matches);

   $imgList = array();
   $imgListText = array();
   $rawImgList = array();
   $rawImgListText = array();
   $rawImgTimes = array();

   $total_time = 0;
   $newestRadarCacheFile = '';
   $lastRadarGMTText ='';
   $newestRadarImgHTML = '';
   $numImages = 0;
   $totalImages = 0;
   $newestImageIdx = 0;
   
   if(isset($matches[1])) { // got the image data.. process it
	   $tstr = $matches[1];
	   preg_match_all('|(\S+)\="([^"]+)"|U',$tstr,$matches);
	   
//       $Status .= "<!-- matches\n" . print_r($matches,true) . " -->\n";
	   foreach ($matches[1] as $i => $key) {
		  if(preg_match('|data-wxo-anim-(\d+)|',$key,$tmatch) ) {
			  $rawImgList[$tmatch[1]] = $matches[2][$i];
		  }
		  if(preg_match('|data-wxo-label-(\d+)|',$key,$tmatch) ) {
			  $rawImgListText[$tmatch[1]] = $matches[2][$i];
		  }
		  if(preg_match('|data-image-count|',$key,$tmatch) ) {
			  $totalImages = $matches[2][$i];
		  }
		  if(preg_match('|data-image-current|',$key,$tmatch) ) {
			  $newestImageIdx = $matches[2][$i];
		  }
	   }
	   $Status .= "<!-- totalImages=$totalImages newestImageIdx=$newestImageIdx -->\n";
   }

   foreach ($rawImgList as $i => $rawImg) {
	   $Status .= "<!-- found img#$i '$rawImg', title='".$rawImgListText[$i]."' -->\n";
   }
   if(!isset($newestImageIdx)) {$newestImageIdx = 6; }
/*
Array
(
    [data-image-current] => 6
    [data-image-count] => 7
    [data-wxo-anim-0] => /data/lightning_images/PAC_201610131400.png
    [data-wxo-label-0] => #1
    [data-wxo-anim-1] => /data/lightning_images/PAC_201610131410.png
    [data-wxo-label-1] => #2
    [data-wxo-anim-2] => /data/lightning_images/PAC_201610131420.png
    [data-wxo-label-2] => #3
    [data-wxo-anim-3] => /data/lightning_images/PAC_201610131430.png
    [data-wxo-label-3] => #4
    [data-wxo-anim-4] => /data/lightning_images/PAC_201610131440.png
    [data-wxo-label-4] => #5
    [data-wxo-anim-5] => /data/lightning_images/PAC_201610131450.png
    [data-wxo-label-5] => #6
    [data-wxo-anim-6] => /data/lightning_images/PAC_201610131500.png
    [data-wxo-label-6] => #7
)
rawImgList
Array
(
    [0] => /data/lightning_images/PAC_201610132030.png
    [1] => /data/lightning_images/PAC_201610132040.png
    [2] => /data/lightning_images/PAC_201610132050.png
    [3] => /data/lightning_images/PAC_201610132100.png
    [4] => /data/lightning_images/PAC_201610132110.png
    [5] => /data/lightning_images/PAC_201610132120.png
    [6] => /data/lightning_images/PAC_201610132130.png
)
 -->
<!-- rawImgListText
Array
(
    [0] => #1
    [1] => #2
    [2] => #3
    [3] => #4
    [4] => #5
    [5] => #6
    [6] => #7
)
*/
   $NOWgmt = time();
   $NOWdate = gmdate("D, d M Y H:i:s", $NOWgmt);
   $Status .= "<!-- now UTC date       $NOWgmt=$NOWdate UTC-->\n";
   preg_match('|_(\d+)\.|',$rawImgList[6],$matches);
   if(isset($matches[1])) {
	   $tYr = substr($matches[1],0,4);
	   $tMo = substr($matches[1],4,2);
	   $tDy = substr($matches[1],6,2);
	   $tHr = substr($matches[1],8,2);
	   $tMi = substr($matches[1],10,2);
	   $newestLightingTime = strtotime("$tYr-$tMo-$tDy $tHr:$tMi:00 GMT");
	   $Status .= "<!-- newest raw file    $newestLightingTime=" . 
	              gmdate("D, d M Y H:i:s", $newestLightingTime).
	              " UTC -->\n";
   } else {
	   $newestLightingTime = time() - $refetchSeconds - 10;
   }
   
   if(file_exists($cacheDir."lightning-{$lightningID}-{$newestImageIdx}.png")) {
	   $newestCacheFileTime = filemtime($cacheDir."lightning-{$lightningID}-{$newestImageIdx}.png");
	   $Status .= "<!-- newest cache file  $newestCacheFileTime=" . 
	              gmdate("D, d M Y H:i:s",$newestCacheFileTime).
	              " UTC -->\n";
   }
   
  
   if( !file_exists($cacheDir."lightning-{$lightningID}-{$newestImageIdx}.png") or
      (file_exists($cacheDir."lightning-{$lightningID}-{$newestImageIdx}.png") and
	   $newestCacheFileTime < $newestLightingTime) ) {
	     $reloadImages = true;
	} else {
	     $reloadImages = false;
	}

	$Status .= "<!-- final forceRefresh=";
	$Status .= $forceRefresh?'true':'false';
	$Status .= " reloadImages=";
	$Status .= $reloadImages?'true':'false';
	$Status .= " -->\n";

 
if ($reloadImages and file_exists($RealCacheName) ) {  // do the reload of the image files if needed
  foreach($rawImgList as $i => $RawImgFile) {
	$didIt = false;
	$time_start = ECL_microtime_float();
	$lightningCacheFile = "lightning-{$lightningID}-{$i}.png";
	$imgURL = $RawImgURL . $RawImgFile;
	//$Status .= "<!-- Loading $imgURL \n to $cacheDir$lightningCacheFile -->\n";

	$didIt = ECL_download($imgURL,$cacheDir,$lightningCacheFile,$new_width,$new_height);
	
	$time_stop = ECL_microtime_float();
	$total_time += ($time_stop - $time_start);
	$time_fetch = sprintf("%01.3f",round($time_stop - $time_start,3));

	if ($didIt) {
	  $Status .= "<!-- loaded $lightningCacheFile in $time_fetch secs, scaled to w,h=$new_width,$new_height. -->\n";
	  } else {
	  $Status .= "<!-- unable to reload $lightningCacheFile ($time_fetch secs.) -->\n";
	}
  } // end foreach
	
  // make thumbnail too for latest image
	$imgname = "lightning-{$lightningID}-{$newestImageIdx}.png"; // get latest image name
	$thumbname = str_replace('-'.$newestImageIdx.'.png','-sm.png',$imgname);
    $time_start = ECL_microtime_float();
	$image = imagecreatefrompng ($cacheDir . $imgname);;  // fetch our radar
	
	if (! $image ) { // oops... no existing image, create a dummy one
       $image  = imagecreate ($new_width, $new_height); //* Create a blank image 
       $bgc = imagecolorallocate ($image, 128, 128, 128); 
       imagefilledrectangle ($image, 0, 0, $new_width, $new_height, $bgc); 
	}
	$MaxX = imagesx($image);
	$MaxY = imagesy($image);
	$image_p = imagecreatetruecolor($thumb_width, $thumb_height);
    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $MaxX, $MaxY);

	if (time() > ($newestLightingTime + $noLightningMinutes*60 + $refetchSeconds + 15)) {
	  // stale radar if > 25 minutes + refetchTime + 15 seconds old
        $text_color = imagecolorallocate ($image_p, 192,51,51);
        $bgcolor = imagecolorallocate ($image, 128, 128, 128); 
		imagefilledrectangle($image_p, 5, 95, 230, 140,$bgcolor);
        imagestring ($image_p, 5, 15, 100, "$ECLNO", $text_color);
        imagestring ($image_p, 5, 15, 120, $imgListText[0], $text_color);
	}
	
    imagepng($image_p, $cacheDir . $thumbname); 
    imagedestroy($image); 
    imagedestroy($image_p); 
		$time_stop = ECL_microtime_float();
	    $total_time += ($time_stop - $time_start);
	    $time_fetch = sprintf("%01.3f",round($time_stop - $time_start,3));
	$Status .= "<!-- small image w=$thumb_width h=$thumb_height saved to $thumbname in $time_fetch secs. -->\n";
	$Status .= "<!-- image files cached in ".sprintf("%01.3f",round($total_time))." secs. -->\n";

} // end if reloadImages

// now setup the list of images+text for the page display

  foreach($rawImgListText as $i => $RawImgText) {
	if(file_exists($cacheDir."lightning-{$lightningID}-{$i}.png")) {
	  $imgList[$i] = "lightning-{$lightningID}-{$i}.png";
	  preg_match('|_(\d+)\.|',$rawImgList[$i],$matches);
      if(isset($matches[1])) {
		 $tYr = substr($matches[1],0,4);
		 $tMo = substr($matches[1],4,2);
		 $tDy = substr($matches[1],6,2);
		 $tHr = substr($matches[1],8,2);
		 $tMi = substr($matches[1],10,2);
		 $rawImgTimes[$i] = "@ $tHr:$tMi UTC";
		 // $newestLightingTime = strtotime("$tYr-$tMo-$tDy $tHr:$tMi:00 GMT");
	  }  else {
		 $rawImgTimes[$i] = '';
	  }

	  $imgListText[$i] = $RawImgText . " ". $rawImgTimes[$i];
	  $numImages++;
	}
  }

if ($imageOnly) {
    $ourImg = $cacheDir . "lightning-$lightningID-sm.png";
    if (file_exists($ourImg)) {
	  $ourImgSize = filesize($ourImg);
	  $ourImgGMT = filectime($ourImg);
	  header("Content-type: image/png"); // now send to browser
	  header("Content-length: " . $ourImgSize);
	  header("Last-modified: " . gmdate("D, d M Y H:i:s", $ourImgGMT) . ' GMT');
	  header("Expires: " . gmdate("D, d M Y H:i:s", $ourImgGMT+$refetchSeconds) . ' GMT');
	  readfile($ourImg);
	}
    exit;
}

// print it out:
if ($printIt && ! $doInclude) {
//------------------------------------------------
header("Cache-Control: no-cache,no-store,  must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$NOWdate = gmdate("D, d M Y H:i:s", time());
header("Expires: $NOWdate GMT");
header("Last-Modified: $NOWdate GMT");
header("Content-type: text/html,charset=\"$charsetOutput\"");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Refresh" content="300" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title><?php print "$siteTitle"; ?></title>
<style type="text/css">
body {
 background-color: #FFFFFF;
}
.EClightning {
  font-family:Verdana, Arial, Helvetica, sans-serif;
  font-size:12px;
  color: #000000;
}
.EClightning p {
  text-align:center;
}
</style>
</head>
<body>
<?php 
}
  print $Status;
  print "<!-- autoplay=";
  print $autoPlay?'true':'false';
  print " -->\n";

if ($printIt) {
  $ECLURL = preg_replace('|&|Ui','&amp;',$ECLURL); // make link XHTML compatible
  // $Status .= "<!-- imgListTxt \n" . print_r($imgListText,true) . " -->\n";
  print "<div class=\"EClightning\">\n";
  ECL_gen_animation($numImages, $lightningID, $lightningDir,$aniSec);
//  print $imgHTML;
  print "<p style=\"width: 620px;margin: 1em auto;\"><a href=\"$ECLURL\">$siteHeading - $ECLNAME</a></p>\n</div> <!-- end of EClightning -->\n";
}
if ($printIt && ! $doInclude) {?>
</body>
</html>
<?php
}

// ----------------------------functions ----------------------------------- 
function ECL_fetchUrlWithoutHang($url,$useFopen) {
// get contents from one URL and return as string 
  global $Status, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=6;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Status .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (ec-lightning.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: text/html,text/plain"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Status .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Status .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
	$cinfo = array();
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  //$Status .= "<!-- cinfo\n".print_r($cinfo,true)." -->\n";
  $Status .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'];
	if(isset($cinfo['primary_ip'])) {
    $Status .= " dest=".$cinfo['primary_ip'];
	}
	if(isset($cinfo['primary_port'])) { 
	  $Status .= " port=".$cinfo['primary_port'];
	}
	if(isset($cinfo['local_ip'])) {
	  $Status .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Status .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Status .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Status .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Status .= "<!-- raw data\n".$data."\n -->\n"; 
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> '200') {
    $Status .= "<!-- headers returned:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-lightning.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-lightning.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = ECL_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = ECL_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Status .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Status .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end ECL_fetchUrlWithoutHang

// --------------------------------------------------------------------------- 

function ECL_download($file_source,$file_dir, $file_target,$width,$height) {
  global $Status;
  // load the source gif and do the overlay, then save the resulting file V2.00
  
  $tIMG = imagecreatetruecolor($width,$height);
  // Enable blend mode and save full alpha channel
  imagealphablending($tIMG, true);
  imagesavealpha($tIMG, true);
  
  $fileTargetLarge = $file_dir . str_replace('.png','-large.png',$file_target);
  ECL_download_image($file_source,$fileTargetLarge);
  $sceIMG = false;
  
  if(file_exists($fileTargetLarge)) {
      $sceIMG = imagecreatefrompng($fileTargetLarge);
  }

  if(!$sceIMG) {
	  $Status .= "<!-- unable to open $file_source for read -->\n";
	  imagedestroy($tIMG);
	  return false;
  }
//  imagecopy($tIMG,$sceIMG,0,0,0,0,$width,$height);
	  $MaxX = imagesx($sceIMG);
	  $MaxY = imagesy($sceIMG);
	  $ratio = $MaxX/$width;
	  $new_height = round($MaxY/$ratio,0);
      imagecopyresampled($tIMG, $sceIMG,  0, 0, 0, 0, $width, $new_height, $MaxX, $MaxY);
  
  if(!imagepng($tIMG, $file_dir . $file_target)) {
	  $Status .= "<!-- unable to open $file_dir$file_target for writing composite image -->\n";
	  imagedestroy($tIMG);
	  return false;
  }
  imagedestroy($tIMG);
  return true;
}

// ------------------------------------------------------------------
	
function ECL_download_image($file_source, $file_target) {
  global $Status;

  $opts = array(
    'http'=>array(
    'method'=>"GET",
    'protocol_version' => 1.1,
    'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
            "Cache-control: max-age=0\r\n" .
            "Connection: close\r\n" .
            "User-agent: Mozilla/5.0 (ec-lightning.php saratoga-weather.org)\r\n"
    ),
    'https'=>array(
    'method'=>"GET",
    'protocol_version' => 1.1,
    'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
            "Cache-control: max-age=0\r\n" .
            "Connection: close\r\n" .
            "User-agent: Mozilla/5.0 (ec-lightning.php saratoga-weather.org)\r\n"
		)
  );

  $context = stream_context_create($opts);

  $rh = fopen($file_source, 'rb',false,$context);
  if(!$rh) {
	  $Status .= "<!-- unable to read $file_source -->\n";
  }
  $wh = fopen($file_target, 'wb');
  if(!$wh) {
	  $Status .= "<!-- unable to write $file_target -->\n";
  }
  if ($rh===false || $wh===false) {
   // error reading or opening file
    return true;
  }
  while (!feof($rh)) {
    if (fwrite($wh, fread($rh, 1024)) === FALSE) {
          $Status .= '<!-- Cannot write to file ('.$file_target.')' ." -->\n";
          return true;
    }
  }
  fclose($rh);
  fclose($wh);
  // No error
  $Status .= "<!-- loaded $file_target \n        from $file_source -->\n";
  return false;
}

// ------------------------------------------------------------------

function ECL_microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

function ECL_gen_animation ( $numImages, $lightningID, $lightningDir, $aniSec) {
// generate JavaScript and control buttons for rotating the images
  global $new_width, $new_height, $siteTitle, $imgListText, $ECLPlay, $ECLPrev, $ECLNext, $ECLNoJS,
    $siteHeading, $noJSMsg, $ECLNO, $TZ, $TZOffsecSecs, $autoPlay, $ECLlegend, $siteDescription;
if ($numImages < 1) {
  print "<p>Sorry, no current lightning images for site $lightningID are available.</p>\n";
  return;
}

if ($numImages > 1) {
  // generate the animation for 2 or more images 
?>
<?php if($siteDescription) { print "<p class=\"EClightning\" style=\"width: 620px; margin: 1em auto;\">$siteDescription</p>\n"; } ?>


<script type="text/javascript">
// <!--
// clever.. we put out buttons only if JavaScript is enabled
document.write( '<p style="width: 620px;margin: 1em auto;"><input type="button" id="<?php echo $lightningID; ?>btnPrev" value="<?php echo $ECLPrev; ?>" onclick="<?php echo $lightningID; ?>LPrev();" />' +
'<input type="button" id="<?php echo $lightningID; ?>bntPlay" value="<?php echo $ECLPlay; ?>" onclick="<?php echo $lightningID; ?>LPlay()" />' +
'<input type="button" id="<?php echo $lightningID; ?>btnNext" value="<?php echo $ECLNext; ?>" onclick="<?php echo $lightningID; ?>LNext();" /></p>' );
// -->
</script>
<p style="width: 620px;margin: 1em auto;"><span id="<?php echo $lightningID; ?>description"><?php 
	$rT = $imgListText[$numImages-1];
	$rN = $numImages;
    print "$siteHeading - $rT, $rN/$rN"; ?></span><br />
<img src="<?php echo $lightningDir . "lightning-$lightningID-{$newestImageIdx}.png"; ?>" alt="<?php echo $siteTitle; ?>" width="<?php echo $new_width; ?>" height="<?php echo $new_height; ?>" id="<?php echo $lightningID; ?>L_Ath_Slide" title="<?php echo $siteTitle; ?>" /></p>
<noscript><p><?php echo $ECLNoJS; ?></p></noscript>
<?php if ($ECLlegend <> '') { print "$ECLlegend"; } ?>

<script type="text/javascript">
/*
Interactive Image slideshow with text description
By Christian Carlessi Salvadó (cocolinks@c.net.gt). Keep this notice intact.
Visit http://www.dynamicdrive.com for script
*/
<?php echo $lightningID; ?>Lg_fPlayMode = 0;
<?php echo $lightningID; ?>Lg_iimg = -1;
<?php echo $lightningID; ?>Lg_imax = 0;
<?php echo $lightningID; ?>Lg_ImageTable = new Array();
<?php echo $lightningID; ?>Lg_dwTimeOutSec=<?php echo $aniSec;?>

function <?php echo $lightningID; ?>LChangeImage(fFwd)
{
  if (fFwd)
   {
    if (++<?php echo $lightningID; ?>Lg_iimg==<?php echo $lightningID; ?>Lg_imax)
      <?php echo $lightningID; ?>Lg_iimg=0;
   }
  else
  {
    if (<?php echo $lightningID; ?>Lg_iimg==0)
      <?php echo $lightningID; ?>Lg_iimg=<?php echo $lightningID; ?>Lg_imax;
       <?php echo $lightningID; ?>Lg_iimg--;
  }
  <?php echo $lightningID; ?>LUpdate();
}

function <?php echo $lightningID; ?>Lgetobject(obj){
  if (document.getElementById)
    return document.getElementById(obj)
  else if (document.all)
    return document.all[obj]
}

function <?php echo $lightningID; ?>LUpdate(){
  <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>L_Ath_Slide").src = <?php echo $lightningID; ?>Lg_ImageTable[<?php echo $lightningID; ?>Lg_iimg][0];
  <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>description").innerHTML = '<?php echo $siteHeading; ?> - '+<?php echo $lightningID; ?>Lg_ImageTable[<?php echo $lightningID; ?>Lg_iimg][1];
	<?php echo $lightningID; ?>LOnImgLoad();
}


function <?php echo $lightningID; ?>LPlay()
{
  <?php echo $lightningID; ?>Lg_fPlayMode = !<?php echo $lightningID; ?>Lg_fPlayMode;
  if (<?php echo $lightningID; ?>Lg_fPlayMode)
   {
    <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>btnPrev").disabled = <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>btnNext").disabled = true;
    <?php echo $lightningID; ?>LNext();
   }
  else 
   {
    <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>btnPrev").disabled = <?php echo $lightningID; ?>Lgetobject("<?php echo $lightningID; ?>btnNext").disabled = false;
   }
}
function <?php echo $lightningID; ?>LOnImgLoad()
{
  if (<?php echo $lightningID; ?>Lg_fPlayMode)
    window.setTimeout("<?php echo $lightningID; ?>LTick()", <?php echo $lightningID; ?>Lg_dwTimeOutSec*1000);
}
function <?php echo $lightningID; ?>LTick() 
{
  if (<?php echo $lightningID; ?>Lg_fPlayMode)
    <?php echo $lightningID; ?>LNext();

}
function <?php echo $lightningID; ?>LPrev()
{
  <?php echo $lightningID; ?>LChangeImage(false);
}
function <?php echo $lightningID; ?>LNext()
{
  <?php echo $lightningID; ?>LChangeImage(true);
}
//current file list/description 
<?php
  for ($i=0;$i<$numImages;$i++) {
    $lightningCacheFile = $lightningDir . "lightning-{$lightningID}-$i.png";
	$rT = $imgListText[$i];
	$rN = $i+1;

    print "{$lightningID}Lg_ImageTable[{$lightningID}Lg_imax++] = new Array (\"$lightningCacheFile\",\"$rT,  $rN/$numImages\");\n"; 
  }
?>
//end current file list/description

<?php if($autoPlay) {echo $lightningID . 'LPlay();' . "\n";} ?>
</script>
<?php

 } // end of if 2 or more images
   else { // only one image 
   ?>
<p style="width: 620px;margin: 1em auto;"><span id="<?php echo $lightningID; ?>description"><?php echo $imgListText[0] . ' 1/1'; ?></span><br />
<img src="<?php echo $lightningDir . "lightning-$lightningID-{$newestImageIdx}.png"; ?>" alt="<?php echo $siteHeading; ?>" width="<?php echo $new_width; ?> " height="<?php echo $new_height; ?>" title="<?php echo $siteHeading; ?>" /> </p>
<?php
 } // end only one image
}

// end ec-lightning.php