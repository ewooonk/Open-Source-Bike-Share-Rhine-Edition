<?php
require("config.php");
require("db.class.php");
require("actions-web.php");
$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<title><?php echo _('OSBS'); ?></title>
<meta name="description" content="bike share">
<meta name="keywords" content="">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<script type="text/javascript" src="js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="js/translations.php"></script>
<script type="text/javascript" src="js/functions.js?150"></script>
<script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
<script src="https://rawgit.com/sitepoint-editors/jsqrcode/master/src/qr_packed.js">
</script>
<script src="qr.js"></script>
<link rel="stylesheet" type="text/css" href="qr.css" />
<?php
if (isset($geojson))
   {
   foreach($geojson as $url)
      {
      echo '<link rel="points" type="application/json" href="',$url,'">'."\n";
      }
   }
?>
<?php if (date("m-d")=="04-01") echo '<script type="text/javascript" src="https://www.yoursystemurl.org/js/april.js"></script>'; ?>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />

<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="css/map.css" />
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
<link rel='shortcut icon' href='https://www.yoursystemurl.org/favicon.ico' type='image/x-icon' />
</head>
<body>
<div id="map"></div>
<div id="sidebar"><div id="overlay"></div>
<div class="row">
        
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
       <h1 class="pull-left"><a href="https://www.yoursystemurl.org/"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Greek_lc_beta.svg/1024px-Greek_lc_beta.svg.png"  width=60 /></a>
   <h1 class="pull-left"></h1>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<div class="row">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <ul class="list-inline">
<?php
if (isloggedin() AND getprivileges($_COOKIE["loguserid"])>0) echo '<li><a href="https://www.yoursystemurl.org/admin.php"><span class="glyphicon glyphicon-cog"></span> ',_('Admin'),'</a></li>';
if (isloggedin())
   {
   if (getusersub($_COOKIE["loguserid"])==1) echo '<li><a href="https://www.yoursystemurl.org/beta/detail.php"> ðŸŽ“ </a>';
   if (getusersub($_COOKIE["loguserid"])==0) echo '<li> <a href="https://www.yoursystemurl.org/beta/detail.php"><span class="glyphicon glyphicon-user"> ',' </span></a>';
   if (getusersub($_COOKIE["loguserid"])==2) echo '<li> <a href="https://www.yoursystemurl.org/beta/detail.php"><span class="glyphicon glyphicon-user"> ',' </span><span class="glyphicon glyphicon-star"> ',' </span></a>';
   echo ' <small>',getusername($_COOKIE["loguserid"]),'</small>';
   if (iscreditenabled()) echo ' (<span id="usercredit" title="',_('Remaining credit'),'">',getusercredit($_COOKIE["loguserid"]),'</span> ',getcreditcurrency(),' )</li>';
   echo '<li><a href="https://www.yoursystemurl.org/beta/command.php?action=logout" id="logout"><span class="glyphicon glyphicon-log-out"></span></a></li>';
   echo '<h2 id="standname"><select id="stands"></select><span id="standcount"></span></h2>'; 
   if (islimitzero($_COOKIE["loguserid"])) echo '<h4><font color="red">Please confirm your e-mail by clicking the link in the registration mail.</font></h4>';
  // echo '<br /><h4>Report an issue</font></h4>';
 //  echo '<br /><form action="https://www.yoursystemurl.org/uploadimage/upload.php" method="post" enctype="multipart/form-data">
////    <input type="file" name="fileToUpload" capture="camera" id="fileToUpload"><br />
//     <button type="submit" name="submit" class="btn btn-primary btn-large col-lg-4"> Upload Image</button>
 // </form>';
   echo '<div id="standinfo"></div>';
   echo '<div id="standphoto"></div>';
   echo '<div id="standbikes"></div>';
   echo '<div id="qrcode"></div>';
 //  echo '<br /><br /><h4>Support</h4>';
 //  echo '<a href="http://www.yoursystemurl.org/report_problem.php"><button class="btn btn-primary btn-large col-lg-4">Report Problem</button>';
 //  echo '<br /><br /><a href="http://www.yoursystemurl.org/upload_image.php"><button class="btn btn-primary btn-large col-lg-4">Upload Image</button>';
   }
// if (isloggedin())
//   {
//   // echo '<li><span class="glyphicon glyphicon-user"></span>';
//   if (getusersub($_COOKIE["loguserid"])==1) echo '<li> <span class="glyphicon glyphicon-leaf"> ',' </span></li>';
//   echo '</a></li>';
//   }
else
    { 
   echo '<li><a href="http://www.yoursystemurl.org/and/register.php"><span class="glyphicon glyphicon-pencil"></span> ',_('Register'),'</a></li>';
    }
?>
   </ul>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>

<?php if (!isloggedin()): ?>
<div id="loginform">
<h2>Log in</h2>
<?php

if (isset($_GET["error"]) AND $_GET["error"]==1) echo '<div class="alert alert-danger" role="alert"><h3>',_('User / phone number or password incorrect! Please, try again.'),'</h3></div>';
elseif (isset($_GET["error"]) AND $_GET["error"]==2) echo '<div class="alert alert-danger" role="alert"><h3>',_('Session timed out! Please, log in again.'),'</h3></div>';
?>
      <form method="POST" action="https://www.yoursystemurl.org/beta/command.php?action=login">
      <div class="row"><div class="col-lg-12">
            <label for="number" class="control-label"><?php if (issmssystemenabled()==TRUE) echo _('Phone number:'); else echo _('Phone number:'); ?></label> <input type="tel" name="number" id="number" class="form-control" />
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
<div class="row">
   <div class="col-lg-12">
   <div id="console">
   </div>
   </div>
</div>
<div class="row">
<div id="standactions" class="btn-group">
  <div class="col-lg-12">
         <button class="btn btn-primary" type="button" id="rent" title="<?php echo _('Choose bike number and rent bicycle. You will receive a code to unlock the bike and the new code to set.'); ?>"><span class="glyphicon glyphicon-log-out"></span> <?php echo _('Rent'); ?> <span class="bikenumber"></span></button>
         &nbsp; &nbsp;
         <div id="test" style="display:inline-block"><span class="bikenumber"></span>
         </div>
  </div>
</div>
</div>
<div class="row"> <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   
<div id="bikephoto"><span class="bikenumber"></span></div>
</div></div>
<div class="row"><div class="col-lg-12">
<br /></div></div>
<div id="rentedbikes"></div>
<div id="standactions" class="btn-group">
 
</div>
<div class="row">
   <div class="input-group">
   <div class="col-lg-12">
   <input type="text" name="notetext" id="notetext" class="form-control" placeholder="<?php echo _('Describe problem'); ?>">
   </div>
   </div>
</div>
<div class="row">
   <div class="btn-group bicycleactions">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
       <button class="btn btn-primary" type="button" id="unlock" title="<?php echo _('Unlock this bicycle.'); ?>"><span class="glyphicon glyphicon-flash"></span> <?php echo _('Unlock'); ?></button> 
       
      <button type="button" class="btn btn-primary" id="return" title="<?php echo _('Return this bicycle to the selected stand.'); ?>"><span class="glyphicon glyphicon-log-in"></span> <?php echo _('Return bicycle'); ?> <span class="bikenumber"></span></button> <div id="andnote">(<?php echo _('and'); ?> <a href="#" id="note" title="<?php echo _('Use this link to open a text field to write in any issues with the bicycle you are returning (flat tire, chain stuck etc.).'); ?>"><?php echo _('report problem'); ?> <span class="glyphicon glyphicon-exclamation-sign"></span></a>)</div> 
   
   </div></div>
</div>
<div class="row">
      <div class="col-lg-12">
<?php if (isloggedin())
   {
   echo '<h4>Support</h4>';
   echo '<a href="http://www.yoursystemurl.org/report_problem.php"><button class="btn btn-warning">Report Problem</button></a>';
   echo ' &nbsp; &nbsp;<a href="http://www.yoursystemurl.org/upload_image.php"><button class="btn btn-light">Upload Image</button>';
   }
?>
   </div>
</div>
</div>
</body>
</html>
