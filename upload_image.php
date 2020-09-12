<?php

require("config.php");
require("db.class.php");
require("actions-web.php");
$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta http-equiv="Content-Type" content="text/html; charset=windows-1252">

<title>OSBS- Upload Image</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/bootstrap.min.js"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/viewportDetect.js"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/leaflet.js"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/translations.php"></script>
<script type="text/javascript" src="https://www.yoursystemurl.org/js/functions.js"></script>
<?php
if (isset($geojson))
   {
   foreach($geojson as $url)
      {
      echo '<link rel="points" type="application/json" href="',$url,'">'."\n";
      }
   }
?>
<?php if (date("m-d")=="04-01") echo '<script type="text/javascript" src="http://maps.stamen.com/js/tile.stamen.js?v1.3.0"></script>'; ?>
<link rel="stylesheet" type="text/css" href="https://www.yoursystemurl.org/css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="https://www.yoursystemurl.org/css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="https://www.yoursystemurl.org/css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="https://www.yoursystemurl.org/css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="https://www.yoursystemurl.org/css/map.css" />
<script>
var maplat=<?php echo $systemlat; ?>;
var maplon=<?php echo $systemlong; ?>;
var mapzoom=<?php echo $systemzoom; ?>;
var standselected=0;
<?php
if (isloggedin())
   {
   echo 'var loggedin=1;',"\n";
   echo 'var priv=',getprivileges($_COOKIE["loguserid"]),";\n";
   }
else
   {
   echo 'var loggedin=0;',"\n";
   echo 'var priv=0;',"\n";
   }
if (iscreditenabled())
   {
   echo 'var creditsystem=1;',"\n";
   }
else
   {
   echo 'var creditsystem=0;',"\n";
   }
if (issmssystemenabled()==TRUE)
   {
   echo 'var sms=1;',"\n";
   }
else
   {
   echo 'var sms=0;',"\n";
   }
?>
</script>
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
<link rel='shortcut icon' href='favicon.ico' type='image/x-icon' />
</head>
<body>
<div id="map"></div>
<div id="sidebar"><div id="overlay"></div>
<div class="row">
        
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
       <h1 class="pull-left"><a href="https://www.yoursystemurl.org/and"><img src="https://www.yoursystemurl.org/img/OSBS_word.png"  width=120 /></a>
   <h1 class="pull-left"></h1>
   </div>
  
</div>
<div class="row">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <ul class="list-inline">
<?php
if (isloggedin())
   {
  echo '<h3>Upload an image</font></h3>';
  echo 'Upload an image to report a broken bike or if you were not able to upload an image after your ride. You can also send an <a href="https://api.whatsapp.com/send?phone=31620755489" target="_blank">WhatsApp message</a>.';
 echo '<br /><br /><form action="https://www.yoursystemurl.org/uploadimage/upload.php" method="post" enctype="multipart/form-data">
 <input type="file" name="fileToUpload" capture="camera" id="fileToUpload"><br />
    <button type="submit" name="submit" class="btn btn-primary btn-large col-lg-4"> Upload Image</button>
 </form>';
   }
// if (isloggedin())
//   {
//   // echo '<li><span class="glyphicon glyphicon-user"></span>';
//   if (getusersub($_COOKIE["loguserid"])==1) echo '<li> <span class="glyphicon glyphicon-leaf"> ',' </span></li>';
//   echo '</a></li>';
//   }

?>
   </ul>
   </div>
<?php if (!isloggedin()): ?>
<div id="loginform">
<h2>Log in</h2>
<?php
if (isset($_GET["error"]) AND $_GET["error"]==1) echo '<div class="alert alert-danger" role="alert"><h3>',_('User / phone number or password incorrect! Please, try again.'),'</h3></div>';
elseif (isset($_GET["error"]) AND $_GET["error"]==2) echo '<div class="alert alert-danger" role="alert"><h3>',_('Session timed out! Please, log in again.'),'</h3></div>';
?>
      <form method="POST" action="command.php?action=login">
      <div class="row"><div class="col-lg-12">
            <label for="number" class="control-label"><?php if (issmssystemenabled()==TRUE) echo _('Phone number:'); else echo _('Phone number:'); ?></label> <input type="text" name="number" id="number" class="form-control" />
       </div></div>
       <div class="row"><div class="col-lg-12">
            <label for="password"><?php echo _('Password:'); ?> <small id="passwordresetblock">(<a id="resetpassword"><?php echo _('Forgotten? Reset password'); ?></a>)</small></label> <input type="password" name="password" id="password" class="form-control" />
       </div></div><br />
       <div class="row"><div class="col-lg-12">
         <button type="submit" id="register" class="btn btn-lg btn-block btn-primary"><?php echo _('Log in'); ?></button>
       </div></div>
         </form>
</div>
<?php endif; ?>
</div>
</body>
</html>