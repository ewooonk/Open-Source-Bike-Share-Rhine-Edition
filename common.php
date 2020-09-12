<?php
require("external/PHPMailer/PHPMailerAutoload.php");
require("external/htmlpurifier/HTMLPurifier.standalone.php");
$htmlpurconfig=HTMLPurifier_Config::createDefault();
$purifier=new HTMLPurifier($htmlpurconfig);
@$purifier->purify($_GET);
@$purifier->purify($_POST);
@$purifier->purify($_COOKIE);
@$purifier->purify($_FILES);
@$purifier->purify($_SERVER);
$locale=$systemlang.".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=".$locale);
bindtextdomain("messages", dirname(__FILE__).'/languages');
textdomain("messages");
if (issmssystemenabled()==TRUE)
   {
   require("connectors/".$connectors["sms"].".php");
   }
else
   {
   require("connectors/disabled.php");
   }
$sms=new SMSConnector();
function error($message)
{
   global $db;
   $db->conn->rollback();
   exit($message);
}
function sendEmail($emailto,$subject,$message)
{
   global $db, $systemname, $systememail, $email;
   $emailto=$db->conn->real_escape_string(trim($emailto));
   $subject=$db->conn->real_escape_string(trim($subject));
   $message=$db->conn->real_escape_string(trim($message));
   $mail=new PHPMailer;
   $mail->isSMTP(); // Set mailer to use SMTP
   //$mail->SMTPDebug  = 2;
   $mail->Host=$email["smtp"]; // Specify main and backup SMTP servers
   $mail->Username=$email["user"]; // SMTP username
   $mail->Password=$email["pass"]; // SMTP password
   $mail->SMTPAuth=true; // Enable SMTP authentication
   $mail->SMTPSecure="ssl"; // Enable SSL
   $mail->Port=465; // TCP port to connect to
   $mail->CharSet="UTF-8";
   $mail->From=$systememail;
   $mail->FromName=$systemname;
   $mail->addAddress($emailto);     // Add a recipient
   $mail->addBCC($systememail);     // Add a recipient
   $mail->Subject=$subject;
   $mail->Body=$message;
   if (DEBUG===FALSE)
      {
      $mail->send();
      }
   else echo $email,' | ',$subject,' | ',$message;
}
function sendSMS($number,$text)
{
   global $sms;
   $message=$text;
   if (strlen($message)>160)
      {
      $message=chunk_split($message,160,"|");
      $message=explode("|",$message);
      foreach ($message as $text)
         {
         $text=trim($text);
         if ($text)
            {
            log_sendsms($number,$text);
            if (DEBUG===TRUE)
               {
               echo $number,' -&gt ',$text,'<br />';
               }
            else
               {
               $sms->Send($number,$text);
               }
            }
         }
      }
   else
      {
      log_sendsms($number,$text);
      if (DEBUG===TRUE)
         {
         echo $number,' -&gt ',$text,'<br />';
         }
      else
         {
         $sms->Send($number,$text);
         }
      }
}
function log_sendsms($number, $text)
{
        global $dbserver,$dbuser,$dbpassword,$dbname;
        $localdb=new Database($dbserver,$dbuser,$dbpassword,$dbname);
        $localdb->connect();
        $localdb->conn->autocommit(TRUE);
        $number = $localdb->conn->real_escape_string($number);
        $text = $localdb->conn->real_escape_string($text);
        $result = $localdb->query("INSERT INTO sent SET number='$number',text='$text'");
}
function generatecodes($numcodes,$codelength,$wastage=25)
{
   // exclude problem chars: B8G6I1l0OQDS5Z2
   // acceptable characters:
   $goodchars='ACEFHJKMNPRTUVWXY4937';
   // build array allowing for possible wastage through duplicate values
   for ($i=0;$i<=$numcodes+$wastage+1;$i++)
      {
      $codes[]=substr(str_shuffle($goodchars),0,$codelength);
      }
   return array_slice($codes,0,($numcodes+1));
}
function getprivileges($userid)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $result = $db->query("SELECT privileges FROM users WHERE userId='$userid'");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["privileges"];
      }
   return FALSE;
}
function getusername($userid)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $result = $db->query("SELECT userName FROM users WHERE userId='$userid'");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["userName"];
      }
   return FALSE;
}
function getphonenumber($userid)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $result = $db->query("SELECT number FROM users WHERE userId='$userid'");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["number"];
      }
   return FALSE;
}
function getuserid($number)
{
   global $db;
   $number=$db->conn->real_escape_string(trim($number));
   $result = $db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["userId"];
      }
   return FALSE;
}
function isloggedin()
{
   global $db;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1) return 1;
      else return 0;
      }
   return 0;
}
function checksession()
{
   global $db,$systemURL;
   $result=$db->query("DELETE FROM sessions WHERE timeStamp<='".time()."'");
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1)
         {
         $timestamp=time()+86400*14;
         $result=$db->query("UPDATE sessions SET timeStamp='$timestamp' WHERE userId='$userid' AND sessionId='$session'");
         $db->conn->commit();
         }
      else
         {
         $result=$db->query("DELETE FROM sessions WHERE userId='$userid' OR sessionId='$session'");
         $db->conn->commit();
         setcookie("loguserid","",time()-86400);
         setcookie("logsession","",time()-86400);
         header("HTTP/1.1 301 Moved permanently");
         header("Location: ".$systemURL."?error=2");
         header("Connection: close");
         exit;
         }
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."?error=2");
      header("Connection: close");
      exit;
      }
}
function logrequest($userid)
{
   global $dbserver,$dbuser,$dbpassword,$dbname;
   $localdb=new Database($dbserver,$dbuser,$dbpassword,$dbname);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);
   $userid=$localdb->conn->real_escape_string(trim($userid));
   $number=getphonenumber($userid);
   $result = $localdb->query("INSERT INTO received SET sender='$number',receive_time='".date("Y-m-d H:i:s")."',sms_text='".$_SERVER['REQUEST_URI']."',ip='".$_SERVER['REMOTE_ADDR']."'");
}
function logresult($userid,$text)
{
   global $dbserver,$dbuser,$dbpassword,$dbname;
   $localdb=new Database($dbserver,$dbuser,$dbpassword,$dbname);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);
   $userid = $localdb->conn->real_escape_string($userid);
   $text = $localdb->conn->real_escape_string($text);
   $logtext="";
   if (is_array($text))
      {
      foreach ($text as $value)
         {
         $logtext.=$value."; ";
         }
      }
   else
      {
      $logtext=$text;
      }
   $logtext = strip_tags($localdb->conn->real_escape_string($logtext));
   $result = $localdb->query("INSERT INTO sent SET number='$userid',text='$logtext'");
}
function checkbikeno($bikeNum)
{
   global $db;
   $bikeNum = $db->conn->real_escape_string($bikeNum);
   $bikeNum=intval($bikeNum);
   $result=$db->query("SELECT bikeNum FROM bikes WHERE bikeNum='$bikeNum'");
   if (!$result->num_rows)
      {
      response('<h3>Bike '.$bikeNum.' does not exist!</h3>',ERROR);
      }
}
function checkstandname($stand)
{
   global $db;
   $stand = $db->conn->real_escape_string($stand);
   $standname=trim(strtoupper($stand));
   $result=$db->query("SELECT standName FROM stands WHERE standName='$stand'");
   if (!$result->num_rows)
      {
      response('<h3>'._('Stand').' '.$stand.' '._('does not exist').'!</h3>',ERROR);
      }
}
/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
**/
function notifyAdmins($message,$notificationtype=0)
{
   global $db,$systemname,$watches;
   $result = $db->query("SELECT number,mail FROM users where privileges & 2 != 0");
   while($row = $result->fetch_assoc())
      {
      if ($notificationtype==0)
         {
         sendSMS($row["number"],$message);
         sendEmail($watches["email"],$systemname." "._('notification'),$message);
         }
      else
         {
         sendEmail($row["mail"],$systemname." "._('notification'),$message);
         }
      }
}
function sendConfirmationEmail($emailto)
{
   global $db, $dbpassword, $systemname, $systemrules, $systemURL;
   $emailto = $db->conn->real_escape_string($emailto);
   $subject = _('Registration');
   $result=$db->query("SELECT userName,userId FROM users WHERE mail='".$emailto."'");
   $row = $result->fetch_assoc();
   $userId=$row["userId"];
   $userKey=hash('sha256', $emailto.$dbpassword.rand(0,1000000));
   $db->query("INSERT INTO registration SET userKey='$userKey',userId='$userId'");
   $db->query("INSERT INTO limits SET userId='$userId',userLimit=0");
   $db->query("INSERT INTO credit SET userId='$userId',credit=0");
   $names=preg_split("/[\s,]+/",$row["userName"]);
   $firstname=$names[0];
   $message=_('Hello').' '.$firstname.",\n\n".
   _('You have been registered into the bike share system of').' '.$systemname.".\n\n".
   
   _('The user guide is available here:')."\n"."https://www.yoursystemurl.org/guide.pdf"."\n\n".
   
   _('Terms and Conditions are available here:')."\n".$systemrules."\n\n".
   
   _('By clicking the following link you agree to the Terms and Conditions:')."\n".$systemURL."agree.php?key=".$userKey;
   sendEmail($emailto,$subject,$message);
}
function confirmUser($userKey)
{
        global $db, $limits;
        $userKey = $db->conn->real_escape_string($userKey);
        $result=$db->query("SELECT userId FROM registration WHERE userKey='$userKey'");
        if($result->num_rows==1)
                {
                        $row = $result->fetch_assoc();
                        $userId = $row["userId"];
                }
                else
                {
                        echo '<div class="alert alert-danger" role="alert">',_('Registration key not found!'),'</div>';
                        return FALSE;
                }
        $result2=$db->query("SELECT mail FROM users WHERE userID ='$userId'");
        if (!$result2->num_rows) response(_('No such user found.'),1);
        $row2 =$result2->fetch_assoc();
        $email=$row2["mail"];
        $limitwl = $limits["registration"];
        if (strpos($email, '@wur.nl') !== false){
            $limitwl =1;
        }       
        $db->query("UPDATE limits SET userLimit='$limitwl' WHERE userId='$userId'");
        $db->conn->commit();
        $db->query("DELETE FROM registration WHERE userId='$userId'");
        echo '<div class="alert alert-success" role="alert">',_('Your account has been activated. Welcome!'),'</div>';
        $db->conn->commit();
        if ($limitwl == 1){
            echo '<div class="alert alert-success" role="alert">',_('Because your email domain is whitelisted you can immediately rent a bike after purchasing credits.'),'</div>';
}
        if ($limitwl == 0){
            echo '<div class="alert alert-success" role="alert">',_('Because your email domain is not whitelisted, please wait upto 24 hours for the final activation by an admin.'),'</div>';
}
}
function checktopofstack($standid)
{
   global $db;
   $standid = $db->conn->real_escape_string($standid);
   $currentbikes=array();
   // find current bikes at stand
   $result=$db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standId='$standid'");
   while($row=$result->fetch_assoc())
      {
      $currentbikes[]=$row["bikeNum"];
      }
   if (count($currentbikes))
      {
      // find last returned bike at stand
      $result=$db->query("SELECT bikeNum FROM history WHERE action IN ('RETURN','FORCERETURN') AND parameter='$standid' AND bikeNum IN (".implode($currentbikes,",").") ORDER BY time DESC LIMIT 1");
      if ($result->num_rows)
         {
         $row=$result->fetch_assoc();
         return $row["bikeNum"];
         }
      }
   return FALSE;
}
function checklongrental()
{
   global $db,$watches,$notifyuser;
   $abusers=""; $found=0;
   $result=$db->query("SELECT bikeNum,currentUser,userName,number FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId WHERE currentStand IS NULL");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $userid=$row["currentUser"];
      $username=$row["userName"];
      $userphone=$row["number"];
      $result2=$db->query("SELECT time FROM history WHERE bikeNum='$bikenum' AND userId='$userid' AND action='RENT' ORDER BY time DESC LIMIT 1");
      if ($result2->num_rows)
         {
         $row2=$result2->fetch_assoc();
         $time=$row2["time"];
         $time=strtotime($time);
         if ($time+($watches["longrental"]*3600)<=time())
            {
            $abusers.=" b".$bikenum." "._('by')." ".$username.",";
            $found=1;
            if ($notifyuser) sendSMS($userphone,_('Please, return your bike ').$bikenum._(' immediately to the closest stand! Ignoring this warning can get you banned from the system.'));
            }
         }
      }
   if ($found)
      {
      $abusers=substr($abusers,0,strlen($abusers)-1);
      notifyAdmins($watches["longrental"]."+ "._('hour rental').":".$abusers);
      }
}
// cron - called from cron by default, set to 0 if from rent function, userid needs to be passed if cron=0
function checktoomany($cron=1,$userid=0)
{
   global $db,$watches;
   $cron = $db->conn->real_escape_string($cron);
   $userid= $db->conn->real_escape_string($userid);
   $abusers=""; $found=0;
   if ($cron) // called from cron
      {
      $result=$db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId");
      while($row=$result->fetch_assoc())
         {
         $userid=$row["userId"];
         $username=$row["userName"];
         $userlimit=$row["userLimit"];
         $currenttime=date("Y-m-d H:i:s",time()-$watches["timetoomany"]*3600);
         $result2=$db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
         if ($result2->num_rows>=($userlimit+$watches["numbertoomany"]))
            {
            $abusers.=" ".$result2->num_rows." ("._('limit')." ".$userlimit.") "._('by')." ".$username.",";
            $found=1;
            }
         }
      }
   else // called from function for user userid
      {
      $result=$db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId WHERE users.userId='$userid'");
      $row=$result->fetch_assoc();
      $username=$row["userName"];
      $userlimit=$row["userLimit"];
      $currenttime=date("Y-m-d H:i:s",time()-$watches["timetoomany"]*3600);
      $result=$db->query("SELECT bikeNum FROM history WHERE userId='$userid' AND action='RENT' AND time>'$currenttime'");
      if ($result->num_rows>=($userlimit+$watches["numbertoomany"]))
         {
         $abusers.=" ".$result->num_rows." ("._('limit')." ".$userlimit.") "._('by')." ".$username.",";
         $found=1;
         }
      }
   if ($found)
      {
      $abusers=substr($abusers,0,strlen($abusers)-1);
      notifyAdmins(_('Over limit in')." ".$watches["timetoomany"]." "._('hs').":".$abusers);
      }
}
function checktoomanyreturns($userid)
{
   global $db;
   $number=getphonenumber($userid);
   $currenttime=date("Y-m-d H:i:s",time()-60*20);
   $result=$db->query("SELECT * FROM received WHERE time > '$currenttime' AND sms_text LIKE '%return%' AND sender = '$number'");
   $rows = $result->num_rows;
   return $rows;
  
}
// check if user has credit >= minimum credit+rent fee+long rental fee
function checkrequiredcredit($userid)
{
   global $db,$credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $requiredcredit=3;
   $requiredcredit = str_replace(',', '.', $requiredcredit);
   $result=$db->query("SELECT credit FROM credit WHERE userId='$userid' AND credit>='$requiredcredit'");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      return TRUE;
      }
   return FALSE;
}
// subtract credit for rental
function changecreditendrental($bike,$userid)
{
   global $db,$watches,$credit,$bikes;
   $bike=$db->conn->real_escape_string(trim($bike));
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $usercredit=getusercredit($userid);
   $result=$db->query("SELECT time FROM history WHERE bikeNum='$bike' AND userId='$userid' AND (action='RENT' OR action='FORCERENT') ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $starttime=strtotime($row["time"]);
      $endtime=time();
      $timediff=$endtime-$starttime;
      $creditchange=0;
      $changelog="";
      $usersub=getusersub($userid);
      $freetime=20;
      if ($usersub==1 AND checkextrabike($userid)==FALSE) $freetime=$watches["freetime"];
      if ($usersub==2 AND checkextrabike($userid)==FALSE) $freetime=$watches["freetime"]*2;
      if ($timediff<$freetime*60)
         {
         $creditchange=$creditchange;
         $changelog.="overfree-".$credit["rent"].";";
         }
      if ($freetime==0) $freetime=0; // for further calculations
      if ($credit["pricecycle"] AND $timediff>$freetime*60) // after first paid period, i.e. freetime*2; if pricecycle enabled
         {
         $temptimediff=$timediff-($freetime*60);
         if ($credit["pricecycle"]==1) // flat price per cycle
            {
            $calcycles = 0;    
            $cycles=ceil($temptimediff/($watches["flatpricecycle"]*60));
            if ($cycles == 37) $calcycles = 7;
            if ($cycles == 38) $calcycles = 8;
            if ($cycles >= 7 AND $cycles < 37) $calcycles = 6;
            if ($cycles <= 6) $calcycles = $cycles;
            $creditchange=$creditchange+($credit["rent"]*$calcycles);
            $changelog.="flat-".$credit["rent"]*$calcycles.";";
            }
         elseif ($credit["pricecycle"]==2) // double price per cycle
            {
            $cycles=ceil($temptimediff/($watches["doublepricecycle"]*60));
            $tempcreditrent=$credit["rent"];
            for ($i=1;$i<=$cycles;$i++)
               {
               $multiplier=$i;
               if ($multiplier>$watches["doublepricecyclecap"])
                  {
                  $multiplier=$watches["doublepricecyclecap"];
                  }
               // exception for rent=1, otherwise square won't work:
               if ($tempcreditrent==1) $tempcreditrent=2;
               $creditchange=$creditchange+pow($tempcreditrent,$multiplier);
               $changelog.="double-".pow($tempcreditrent,$multiplier).";";
               }
            }
         }
    
      
      
      
      $result4=$db->query("SELECT shared FROM bikes WHERE bikeNum=$bike");
      $row4=$result4->fetch_assoc();
      $bikeshared=$row4["shared"];
      
      
      
      
      if($bikeshared==1) $creditchange=0;
      
      
      
      
      
      $usercredit=$usercredit-$creditchange;
      $usercredit = str_replace(',', '.', $usercredit);
      $usersoc=$usersoc+1;
      $usersoc = str_replace(',', '.', $usersoc);      
      $result=$db->query("UPDATE credit SET credit='$usercredit' WHERE userId='$userid'");
      $result=$db->query("UPDATE credit SET soc='$usersoc' WHERE userId='$userid'");
      $result=$db->query("INSERT INTO history SET userId='$userid',bikeNum='$bike',action='CREDITCHANGE',parameter='".$creditchange."|".$changelog."'");
      $result=$db->query("INSERT INTO history SET userId='$userid',bikeNum='$bike',action='CREDIT',parameter='$usercredit'");
      return array($creditchange, $timediff);      
      // return $creditchange;
      }
}
function iscreditenabled()
{
   global $credit;
   if ($credit["enabled"]) return TRUE;
   return FALSE;
}
function getusercredit($userid)
{
   global $db,$credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT credit FROM credit WHERE userId='$userid'");
   $row=$result->fetch_assoc();
   $usercredit=$row["credit"];
   return $usercredit;
}
function getusersub($userid)
{
   global $db,$credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT sub FROM credit WHERE userId='$userid'");
   $row=$result->fetch_assoc();
   $usersub=$row["sub"];
   return $usersub;
}
function getusersoc($userid)
{
   global $db,$credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT soc FROM credit WHERE userId='$userid'");
   $row=$result->fetch_assoc();
   $usersoc=$row["soc"];
   return $usersoc;
}

function getusermandate($userid)
{
   global $db,$credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT mollieId FROM credit WHERE userId='$userid'");
   $row=$result->fetch_assoc();
   $usermandate=$row["mollieId"];
   return $usermandate;
}

function islimitzero($userid)
{
   global $db,$limits;
   $userid=$db->conn->real_escape_string(trim($userid));
   $result=$db->query("SELECT userLimit FROM limits WHERE userId='$userid'");
   $row=$result->fetch_assoc();
   $userlim=$row["userLimit"];
   if ($userlim==0) return TRUE;
   return FALSE;
}
function checkextrabike($userId)
{
    global $db;
    $userId=$db->conn->real_escape_string(trim($userId));
    $result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser='$userId'");
    $row = $result->fetch_assoc();
    $countRented = $row["countRented"];
    $result=$db->query("SELECT userLimit FROM limits where userId='$userId'");
    $row = $result->fetch_assoc();
    $limit = $row["userLimit"];
    if ($countRented>0) return TRUE;
    return FALSE;
}
function getcreditcurrency()
{
   global $credit;
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   return $credit["currency"];
}
function issmssystemenabled()
{
   global $connectors;
   if ($connectors["sms"]=="") return FALSE;
   return TRUE;
}
function normalizephonenumber($number)
{
   global $countrycode;
   $number=str_replace("+","",$number);
   $number=str_replace(" ","",$number);
   $number=str_replace("-","",$number);
   $number=str_replace("/","",$number);
   $number=str_replace(".","",$number);
   if (substr($number,0,1)=="0") $number=$countrycode.substr($number,1);
 //  if (substr($number,0,3)<>$countrycode) $number=$countrycode.$number;
   return $number;
}
?>