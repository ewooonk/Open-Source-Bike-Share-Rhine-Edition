<?php
require("common.php");
require $_SERVER['DOCUMENT_ROOT'] . "/shop/credit/recurring_aut.php";
function response($message,$error=0,$additional="",$log=1)
{
   global $db;
   // $message=$db->conn->real_escape_string(trim($message));
   // $error=$db->conn->real_escape_string(trim($error));
   // $additional=$db->conn->real_escape_string(trim($additional));
   // $log=$db->conn->real_escape_string(trim($log));
   $json=array("error"=>$error,"content"=>$message);
   if (is_array($additional))
      {
      foreach ($additional as $key=>$value)
         {
         $json[$key]=$value;
         }
      }
   $json=json_encode($json);
   if ($log==1 AND $message)
      {
      if (isset($_COOKIE["loguserid"]))
         {
         $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
         }
      else $userid=0;
      $number=getphonenumber($userid);
      logresult($number,$message);
      }
   $db->conn->commit();
   echo $json;
   exit;
}
function rent($userId,$bike,$geofenceidd,$force=FALSE)
{
   global $db,$forcestack,$watches,$credit;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bike=$db->conn->real_escape_string(trim($bike));
   $geofenceidd=$db->conn->real_escape_string(trim($geofenceidd));
   $force=$db->conn->real_escape_string(trim($force));
   $stacktopbike=FALSE;
   $bikeNum = $bike;
   $requiredcredit=3;
   $requiredcredit = str_replace(',', '.', $requiredcredit);
   if ($force==FALSE)
      {
      $creditcheck=checkrequiredcredit($userId);
      if ($creditcheck===FALSE)
         {
         response(_('Your credit is below the maximum 12 hour fee (3).')." "._('Please, recharge your credit.'),3);
         }
      if ($geofenceidd==1)
         {
         response(_('You are too far away from the stand.')." "._('Please, choose a nearby stand. Turning on Wifi might improve location accuracy, even if you are not connected to a network. <br /><br /> If this issue still occurs, use <a href="https://www.yoursystemurl.org/beta/scan.php/rent/'.$bikeNum.'"  target="_blank">this link</a> to receive the code of this bike.'),4);
         }
      if ($geofenceidd==561)
         {
         response(_('The web-app has no access to your location.')." "._('Please, enable GPS location services and allow your webbrowser and this website acces to your location. <a href="https://system_help_page" target="_blank">open help</a> <br /><br /> If this issue still occurs, use <a href="https://www.yoursystemurl.org/scan.php/rent/'.$bikeNum.'"  target="_blank">this link</a> to receive the code of this bike.'),4);
         }   
      checktoomany(0,$userId);
      $result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser='$userId'");
      $row = $result->fetch_assoc();
      $countRented = $row["countRented"];
      $result=$db->query("SELECT userLimit FROM limits where userId='$userId'");
      $row = $result->fetch_assoc();
      $limit = $row["userLimit"];
      if ($countRented>=$limit)
         {
         if ($limit==0)
            {
            response(_('You can not rent any bikes. Contact the admins to lift the ban.'),ERROR);
            }
         elseif ($limit==1)
            {
            response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$limit),$limit)." "._('at once').".",ERROR);
            }
         else
            {
            response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$limit),$limit)." "._('at once')." "._('and you have already rented')." ".$limit.".",ERROR);
            }
         }
      // check if shared bike
      $result=$db->query("SELECT shared FROM bikes WHERE bikeNum='$bike'");
      $row=$result->fetch_assoc();
      $shared=$row["shared"];
      
      // get userphone 
      $result=$db->query("SELECT number FROM users WHERE userId='$userId'");
      $row=$result->fetch_assoc();
      $userphone=$row["number"];
      
      // get usermail domain
      $result=$db->query("SELECT mail FROM users WHERE userId='$userId'");
      $row=$result->fetch_assoc();
      $usermail=$row["mail"];
      $usermaildomain=substr(strrchr($usermail, "@"), 1);
      
      $result4=$db->query("SELECT phone1, phone2, phone3, email FROM sharing WHERE bikeNum='$bikeNum'");
      $row=$result4->fetch_assoc();
      if ($shared==1 and !in_array($userphone, $row) and !in_array($usermaildomain, $row)) 
         {
          
          response(_('This bike is only available for friends / collegues of the owner.'),ERROR);
          
          }
          
     
           
      if ($forcestack OR $watches["stack"])
         {
         $result=$db->query("SELECT currentStand FROM bikes WHERE bikeNum='$bike'");
         $row=$result->fetch_assoc();
         $standid=$row["currentStand"];
         $stacktopbike=checktopofstack($standid);
         if ($watches["stack"] AND $stacktopbike<>$bike)
            {
            $result=$db->query("SELECT standName FROM stands WHERE standId='$standid'");
            $row=$result->fetch_assoc();
            $stand=$row["standName"];
            $user=getusername($userId);
            notifyAdmins(_('Bike')." ".$bike." "._('rented out of stack by')." ".$user.". ".$stacktopbike." "._('was on the top of the stack at')." ".$stand.".",1);
            }
         if ($forcestack AND $stacktopbike<>$bike)
            {
            response(_('Bike')." ".$bike." "._('is not rentable now, you have to rent bike')." ".$stacktopbike." "._('from this stand').".",ERROR);
            }
         }
      }
   $result=$db->query("SELECT currentUser,currentCode,bikelock FROM bikes WHERE bikeNum='$bikeNum'");
   $row=$result->fetch_assoc();
   $currentCode=sprintf("%04d",$row["currentCode"]);
   $currentUser=$row["currentUser"];
   $bikelock=$row["bikelock"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
   $note="";
   while ($row=$result->fetch_assoc())
      {
      $note.=$row["note"]."; ";
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
   $codereset=sprintf("%04d",rand(1,4));
   if ($codereset == 5)
   {
       { $newCode = sprintf("%04d",rand(100,9900));
       }
   }
   if ($codereset != 5)
   {
       { $newCode=$currentCode;
       }
       
   }
   // do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).
   if ($force==FALSE)
      {
      if ($currentUser==$userId)
         {
         response(_('You already rented bike')." ".$bikeNum.". "._('Code is')." ".$currentCode.".",ERROR);
         return;
         }
      if ($currentUser!=0)
         {
         response(_('Bike')." ".$bikeNum." "._('is already rented').".",ERROR);
         return;
         }
      }
   if ($codereset == 5)
   {
       { $message='<h3>'._('Bike').' '.$bikeNum.': <span class="label label-primary">'._('Open with code').' '.$currentCode.'.</span></h3>'._('Change code immediately to').' <span class="label label-default">'.$newCode.'</span><br />'._('(open, move pin on the bottom to position B, set new code, move pin back to position A)').'.';
       }
   }
   if ($codereset != 5)
   {
       { $message='<h3>'._('Bike').' '.$bikeNum.':</span></h3>';
           if ($currentCode!=0) $message.='<h3><span class="label label-primary">'._('Open chain with code').' '.$currentCode.'.</span></h3>';
       if ($bikelock!=0) $message.='<h3><span class="label label-primary">'._('Lock opens automagically').'.</span></h3>';
       if ($currentCode!=0) $message.= '<br />'._('Please').', <strong>'._('rotate the lockpad to').' <span class="label label-default">0000</span></strong> '._('during your ride').'.';
       $message.="<br /><br />"._('Please, check if the brakes, lights and bicycle bell function properly before starting your ride.');
        $message.="<br /><br />"._('In order to return the bike, please select the destination from the menu above.');
       if ($bikelock!=0) lock(0,$userId,$bikeNum);
       }
   }

   if ($note)
      {
      $message.="<br />"._('Reported issue').": <em>".$note."</em>";
      }
   $result=$db->query("UPDATE bikes SET currentUser='$userId',currentCode='$newCode',currentStand=NULL WHERE bikeNum='$bikeNum'");
   if ($force==FALSE)
      {
      $result=$db->query("INSERT INTO history SET userId='$userId',bikeNum='$bikeNum',action='RENT',parameter='$newCode'");
      }
   else
      {
      $result=$db->query("INSERT INTO history SET userId='$userI'd',bikeNum='$bikeNum',action='FORCERENT',parameter='$newCode'");
      }
   response($message);
}
function returnBike($userId,$bike,$stand,$note="",$geofenceidd,$force=FALSE)
{
   global $db;
   $bikeNum = intval($bike);
   $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $userId=$db->conn->real_escape_string(trim($userId));
   $stand=$db->conn->real_escape_string(trim($stand));
   $note=$db->conn->real_escape_string(trim($note));
   $geofenceidd=$db->conn->real_escape_string(trim($geofenceidd));
   $force=$db->conn->real_escape_string(trim($force));
   $stand = strtoupper($stand);
   $noreturns = checktoomanyreturns($userId);
   if ($force==FALSE)
      {
      if ($geofenceidd==1 and $noreturns < 3)
         {
         response(_('You are too far away from the stand.')." "._('Please, choose a nearby stand. Turning on Wifi might improve location accuracy, even if you are not connected to a network. Otherwise, try reloading the page.'),4);
         }
      if ($geofenceidd==561 and $noreturns < 3)
         {
         response(_('The web-app has no access to your location.')." "._('Please, enable GPS location services and allow your webbrowser and this website acces to your location. <a href="https://system_help_page" target="_blank">open help</a>'),4);
         }
      if ($geofenceidd==561 and $noreturns < 4)
         {
         response(_('The web-app has still no access to your location.')." "._('Please, click the return button one more time and upload a picture in the next screen.'),4);
         }
      $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser='$userId' ORDER BY bikeNum");
      $bikenumber=$result->num_rows;
      if ($bikenumber==0)
         {
         response(_('You currently have no rented bikes.'),ERROR);
         }
      }
   if ($force==FALSE)
      {
      $result=$db->query("SELECT currentCode,bikelock FROM bikes WHERE currentUser='$userId' and bikeNum='$bikeNum'");
      }
   else
      {
      $result=$db->query("SELECT currentCode FROM bikes WHERE bikeNum='$bikeNum'");
      }
   $row=$result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);
   $bikelock = $row["bikelock"];
   $result=$db->query("SELECT standId FROM stands WHERE standName='$stand'");
   $row = $result->fetch_assoc();
   $standId = $row["standId"];
   $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand='$standId' WHERE bikeNum='$bikeNum' and currentUser='$userId'");
   if ($note) addNote($userId,$bikeNum,$note);
   $variable = checktoomanyreturns($userId);
   $message='<h3>'._('Bike').' '.$bikeNum.':</h3>';
   if ($bikelock!=0) $message.='<h3><span class="label label-primary">'._('Close smart lock').'.</span></h3>';
   if ($currentCode!=0) $message.= '<h3><span class="label label-primary">'._('Lock with code').' '.$currentCode.'.</span></h3>';
   if ($currentCode!=0) $message.= '<br />'._('Please').', <strong>'._('rotate the lockpad to').' <span class="label label-default">0000</span></strong> '._('when leaving').'.';
   if ($currentCode!=0 and $bikelock!=0) $message.= '<br />'._('You can use the chain to lock the bike frame to a bike rack. Lock the front wheel if not reachable.');
   if ($currentCode!=0 and $bikelock==0) $message.= '<br />'._('Use the chain to lock the bike frame to a bike rack. Lock the rear wheel if not reachable.');
   $message.='<br /><br />'._('Please upload an image to confirm the bike is properly locked at the chosen destination.');
  $message.= '<br /><br /><form action="uploadimage/upload.php" method="post" enctype="multipart/form-data">
   
    <input type="file" name="fileToUpload" capture="camera" id="fileToUpload"><br />
     <button type="submit" name="submit" class="btn btn-primary btn-large col-lg-12"> Upload Image</button>
  </form>';
   if ($note) $message.='<br />'._('You have also reported this problem:').' '.$note.'.';
   if ($force==FALSE)
      {
      $changearray=changecreditendrental($bikeNum,$userId);      
      $creditchange=$changearray[0];
      $timediff=0;
      if ($changearray[1] != null) $timediff=$changearray[1];
      $timediff=gmdate("H:i", $timediff); 
      if (iscreditenabled() AND $creditchange) $message.='<br />'._('Trip duration').': '.$timediff.'.<br />'._('Credit change').': -'.$creditchange.getcreditcurrency().'.';
            $creditcheck=checkrequiredcredit($userId);
      if ($creditcheck===FALSE)
         {
         if (getusermandate($userId)!=Null)
             {
               $message.='<br /><br />'._('Your credit is below maximum 12 hour fee (3). Because you enabled recurring payments, your credit will be automatically recharged.').' ';
              // recurring(getusermandate($userId));
              //  addcreditmandate($userId);
             }
             else
             {
         $message.='<br /><br />'._('Your credit is below maximum 12 hour fee (3). Please, recharge your credit before your next ride.').' ';
             }
         }
      $result=$db->query("INSERT INTO history SET userId='$userId',bikeNum='$bikeNum',action='RETURN',parameter='$standId'");
      }
   else
      {
      $result=$db->query("INSERT INTO history SET userId='$userId',bikeNum='$bikeNum',action='FORCERETURN',parameter='$standId'");
      }
   response($message);
}
function where($userId,$bike)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bike=$db->conn->real_escape_string(trim($bike));
   $bikeNum = $bike;
   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum='$bikeNum'");
   $row = $result->fetch_assoc();
   $phone= $row["number"];
   $userName= $row["userName"];
   $standName= $row["standName"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
   $note="";
   while ($row=$result->fetch_assoc())
      {
      $note.=$row["note"]."; ";
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
   if ($note)
      {
      $note=_('Bike note:')." ".$note;
      }
   if ($standName)
      {
    //  response('<h3>'._('Bike').' '.$bikeNum.' '._('at').' <span class="label label-primary">'.$standName.'</span>.</h3>'.$note);
    return '<h3>'._('Bike').' '.$bikeNum.' '._('at').' <span class="label label-primary">'.$standName.'</span>.</h3>';
      }
   else
      {
      // response('<h3>'._('Bike').' '.$bikeNum.' '._('rented by').' <span class="label label-primary">'.$userName.'</span>.</h3>'._('Phone').': <a href="tel:+'.$phone.'">+'.$phone.'</a>. '.$note);
      
    //  return 'test';
      }
      
      
}

function wheremybike($userId)
{
   global $db, $sharing;
   $userId=$db->conn->real_escape_string(trim($userId));
   $result3=$db->query("SELECT bikeNum FROM sharing WHERE userId=$userId");
   $row3=$result3->fetch_assoc();
   $bike=$row3["bikeNum"];
   $bikeNum = $bike;
   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum='$bikeNum'");
   $row = $result->fetch_assoc();
   $phone= $row["number"];
   $userName= $row["userName"];
   $standName= $row["standName"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
   $note="";
   while ($row=$result->fetch_assoc())
      {
      $note.=$row["note"]."; ";
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
   if ($note)
      {
      $note=_('Bike note:')." ".$note;
      }
   if ($standName)
      {
    //  response('<h3>'._('Bike').' '.$bikeNum.' '._('at').' <span class="label label-primary">'.$standName.'</span>.</h3>'.$note);
    return '<h3>'._('Bike').' '.$bikeNum.' '._('at').' <span class="label label-primary">'.$standName.'</span>.</h3>';
      }
   else
      {
      // response('<h3>'._('Bike').' '.$bikeNum.' '._('rented by').' <span class="label label-primary">'.$userName.'</span>.</h3>'._('Phone').': <a href="tel:+'.$phone.'">+'.$phone.'</a>. '.$note);
      
    //  return 'test';
      }
      
      
}

function addnote($userId,$bikeNum,$message)
{
   global $db, $bikes;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $userNote=$db->conn->real_escape_string(trim($message));
   $result=$db->query("SELECT userName,number from users where userId='$userId'");
   $row=$result->fetch_assoc();
   $userName=$row["userName"];
   $phone=$row["number"];
   $result=$db->query("SELECT stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId WHERE bikeNum='$bikeNum'");
   $row=$result->fetch_assoc();
   $standName=$row["standName"];
   if ($standName!=NULL)
      {
      $bikeStatus=_('at')." ".$standName;
      }
      else
      {
      $bikeStatus=_('used by')." ".$userName." +".$phone;
      }
   $db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
   $noteid=$db->conn->insert_id;
   
   // check if userbike
   
   
   if (userbike)
        {
       
        // get bike owner id
   
   
   
        // get bike owner mail
   
   
        // send email
        
        
        
        }
        else
        {
        notifyAdmins(_('Note #').$noteid.": b.".$bikeNum." (".$bikeStatus.") "._('by')." ".$userName."/".$phone.":".$userNote);
        }
}
function listbikes($stand)
{
   global $db,$forcestack;
   $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
   $stacktopbike=FALSE;
   $stand=$db->conn->real_escape_string($stand);
   if ($forcestack)
      {
      $result=$db->query("SELECT standId FROM stands WHERE standName='$stand'");
      $row=$result->fetch_assoc();
      $stacktopbike=checktopofstack($row["standId"]);
      }
   $result=$db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand'");   
   // $result=$db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand' AND shared='0'");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $result2=$db->query("SELECT note FROM notes WHERE bikeNum='$bikenum' AND deleted IS NULL ORDER BY time DESC");
      $result3=$db->query("SELECT bikeNum FROM sharing WHERE bikeNum='$bikenum'");
      $note="";
      $shared="";
      while ($row=$result3->fetch_assoc())
         {
         $shared.=$row["shared"]."; ";
         }
      while ($row=$result2->fetch_assoc())
         {
         $note.=$row["note"]."; ";
         }
      $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
      if ($note)
         {
         $bicycles[]="*".$bikenum; // bike with note / issue
         $notes[]=$note;
         }
      else if ($shared)
      {   // test if user is allowed to rent this bike
          $result5=$db->query("SELECT number FROM users WHERE userId='$userid'");
          $row5=$result5->fetch_assoc();
          $userphone=$row5["number"];
          $usermaildomain="@wur.nl";
          // user phone numer in shared bikenum
          $result4=$db->query("SELECT phone1, phone2, phone3, email FROM sharing WHERE bikeNum='$bikenum'");
          $row=$result4->fetch_assoc();
          $bikephone=$row["phone1"];
          if (in_array($userphone, $row) or in_array($usermaildomain, $row)) $bicycles[]="@".$bikenum;
          if (in_array($userphone, $row) or in_array($usermaildomain, $row)) $notes[]="";
      }
      else
         {
         $bicycles[]=$bikenum;
         $notes[]="";
         }
      }
      
   if (!$result->num_rows)
      {
      $bicycles="";
      $notes="";
      }
   response($bicycles,0,array("notes"=>$notes,"stacktopbike"=>$stacktopbike),0);
}
function liststands()
{
   global $db;
   response(_('not implemented'),0,"",0); exit;
   $result=$db->query("SELECT standId,standName,standDescription,standPhoto,serviceTag,placeName,longitude,latitude FROM stands ORDER BY standName");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $result2=$db->query("SELECT note FROM notes WHERE bikeNum='$bikenum' AND deleted IS NULL ORDER BY time DESC");
      $note="";
      while ($row=$result2->fetch_assoc())
         {
         $note.=$row["note"]."; ";
         }
      $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
      if ($note)
         {
         $bicycles[]="*".$bikenum; // bike with note / issue
         $notes[]=$note;
         }
      else
         {
         $bicycles[]=$bikenum;
         $notes[]="";
         }
      }
   response($stands,0,"",0);
}
function removenote($userId,$bikeNum)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $result=$db->query("DELETE FROM notes WHERE bikeNum='$bikeNum' LIMIT XXXX");
   response(_('Note for bike')." ".$bikeNum." "._('deleted').".");
}
function last($userId,$bike=0)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bike=$db->conn->real_escape_string(trim($bike));
   $bikeNum=intval($bike);
   if ($bikeNum)
      {
      $result=$db->query("SELECT userName,parameter,standName,action,time FROM `history` JOIN users ON history.userid=users.userid LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum='$bikeNum' AND (action NOT LIKE '%CREDIT%') ORDER BY time DESC LIMIT 10");
      $historyInfo="<h3>"._('Bike')." ".$bikeNum." "._('history').":</h3><ul>";
      while($row=$result->fetch_assoc())
         {
         $time=strtotime($row["time"]);
         $historyInfo.="<li>".date("d/m H:i",$time)." - ";
         if($row["standName"]!=NULL)
            {
            $historyInfo.=$row["standName"];
            if (strpos($row["parameter"],"|"))
               {
               $revertcode=explode("|",$row["parameter"]);
               $revertcode=$revertcode[1];
               }
            if ($row["action"]=="REVERT") $historyInfo.=' <span class="label label-warning">'._('Revert').' ('.str_pad($revertcode,4,"0",STR_PAD_LEFT).')</span>';
            }
         else
            {
            $historyInfo.=$row["userName"].' (<span class="label label-default">'.str_pad($row["parameter"],4,"0",STR_PAD_LEFT).'</span>)';
            }
         $historyInfo.="</li>";
         }
      $historyInfo.="</ul>";
      }
   else
      {
      $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser<>''");
      $inuse=$result->num_rows;
      $result=$db->query("SELECT bikeNum,userName,standName,users.userId FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId LEFT JOIN stands ON bikes.currentStand=stands.standId ORDER BY bikeNum");
      $total=$result->num_rows;
      $historyInfo="<h3>"._('Current network usage:')."</h3>";
      $historyInfo.="<h4>".sprintf(ngettext('%d bicycle','%d bicycles',$total),$total).", ".$inuse." "._('in use')."</h4><ul>";
      while($row=$result->fetch_assoc())
         {
         $historyInfo.="<li>".$row["bikeNum"]." - ";
         if($row["standName"]!=NULL)
            {
            $historyInfo.=$row["standName"];
            }
         else
            {
            $historyInfo.='<span class="bg-warning">'.$row["userName"];
            $result2=$db->query("SELECT time FROM history WHERE bikeNum=".$row["bikeNum"]." AND userId=".$row["userId"]." AND action='RENT' ORDER BY time DESC");
            $row2=$result2->fetch_assoc();
            $historyInfo.=": ".date("d/m H:i",strtotime($row2["time"])).'</span>';
            }
         $result2=$db->query("SELECT note FROM notes WHERE bikeNum='".$row["bikeNum"]."' AND deleted IS NULL ORDER BY time DESC");
         $note="";
         while ($row=$result2->fetch_assoc())
            {
            $note.=$row["note"]."; ";
            }
         $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
         if ($note) $historyInfo.=" (".$note.")";
         $historyInfo.="</li>";
         }
      $historyInfo.="</ul>";
      }
   response($historyInfo,0,"",0);
}
function userbikes($userId)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   if (!isloggedin()) response("");
   $result=$db->query("SELECT bikeNum,currentCode,bikelock FROM bikes WHERE currentUser='$userId' ORDER BY bikeNum");
   while ($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $bicycles[]=$bikenum;
      $codes[]=str_pad($row["currentCode"],4,"0",STR_PAD_LEFT);
      $bikelocks[]=$row["bikelock"];
      $result2=$db->query("SELECT parameter FROM history WHERE bikeNum='$bikenum' AND action='RENT' ORDER BY time DESC LIMIT 1,1");
      $row=$result2->fetch_assoc();
      $oldcodes[]=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if (!$result->num_rows) $bicycles="";
   if (!isset($codes)) $codes="";
   else $codes=array("codes"=>$codes,"oldcodes"=>$oldcodes,"bikelocks"=>$bikelocks);
   response($bicycles,0,$codes,0);
}
function revert($userId,$bikeNum)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $standId=0;
   $result=$db->query("SELECT currentUser FROM bikes WHERE bikeNum='$bikeNum' AND currentUser IS NOT NULL");
   if (!$result->num_rows)
      {
      response(_('Bicycle')." ".$bikeNum." "._('is not rented right now. Revert not successful!'),ERROR);
      return;
      }
   else
      {
      $row=$result->fetch_assoc();
      $revertusernumber=getphonenumber($row["currentUser"]);
      }
   $result=$db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter WHERE bikeNum='$bikeNum' AND action IN ('RETURN','FORCERETURN') ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $standId=$row["parameter"];
      $stand=$row["standName"];
      }
   $result=$db->query("SELECT parameter FROM history WHERE bikeNum='$bikeNum' AND action IN ('RENT','FORCERENT') ORDER BY time DESC LIMIT 1,1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $code=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if ($standId and $code)
      {
      $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand='$standId',currentCode='$code' WHERE bikeNum='$bikeNum'");
      $result=$db->query("INSERT INTO history SET userId='$userId',bikeNum='$bikeNu'm,action='REVERT',parameter='$standId|$code'");
      $result=$db->query("INSERT INTO history SET userId=0,bikeNum='$bikeNum',action='RENT',parameter='$code'");
      $result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      response('<h3>'._('Bicycle').' '.$bikeNum.' '._('reverted to').' <span class="label label-primary">'.$stand.'</span> '._('with code').' <span class="label label-primary">'.$code.'</span>.</h3>');
      sendSMS($revertusernumber,_('Bike')." ".$bikeNum." "._('has been returned. You can now rent a new bicycle.'));
      }
   else
      {
      response(_('No last stand or code for bicycle')." ".$bikeNum." "._('found. Revert not successful!'),ERROR);
      }
}
function register($number,$code,$checkcode,$fullname,$email,$password,$password2,$existing)
{
   global $db, $dbpassword, $countrycode, $systemURL;
   $number=$db->conn->real_escape_string(trim($number));
   $code=$db->conn->real_escape_string(trim($code));
   $checkcode=$db->conn->real_escape_string(trim($checkcode));
   $fullname=$db->conn->real_escape_string(trim($fullname));
   $email=$db->conn->real_escape_string(trim($email));
   $password=$db->conn->real_escape_string(trim($password));
   $password2=$db->conn->real_escape_string(trim($password2));
   $existing=$db->conn->real_escape_string(trim($existing));
   $parametercheck=$number.";".str_replace(" ","",$code).";".$checkcode;
   if ($password<>$password2)
      {
      response(_('Password do not match. Please correct and try again.'),ERROR);
      }
   if (issmssystemenabled()==TRUE)
      {
      $result=$db->query("SELECT parameter FROM history WHERE userId=0 AND bikeNum=0 AND action='REGISTER' AND parameter='$parametercheck' ORDER BY time DESC LIMIT 1");
      if ($result->num_rows==1)
         {
         if (!$existing) // new user registration
            {
            $result=$db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='$number',privileges=0");
            $userId=$db->conn->insert_id;
            sendConfirmationEmail($email);
            response(_('You have been successfully registered. Please, check your email and read the instructions to finish your registration.'));
            }
         else // existing user, password change
            {
            $result=$db->query("SELECT userId FROM users WHERE number='$number'");
            $row=$result->fetch_assoc();
            $userId=$row["userId"];
            $result=$db->query("UPDATE users SET password=SHA2('$password',512) WHERE userId='$userId'");
            response(_('Password successfully changed. Your username is your phone number. Continue to').' <a href="'.$systemURL.'">'._('login').'</a>.');
            }
         }
      else
         {
         response(_('Problem with the SMS code entered. Please check and try again.'),ERROR);
         }
      }
   else // SMS system disabled
      {
      $result=$db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='',privileges=0");
      $userId=$db->conn->insert_id;
      $result=$db->query("UPDATE users SET number='$userId' WHERE userId='$userId'");
      sendConfirmationEmail($email);
      response(_('You have been successfully registered. Please, check your email and read the instructions to finish your registration. Your number for login is:')." ".$userId);
      }
}
function updateemail($newemail)
{
   global $db, $systemname, $systemrules, $systemURL;
   $newemail=$db->conn->real_escape_string(trim($newemail));
   $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
   $result=$db->query("SELECT mail FROM users WHERE userId='$userid'");
   if (!$result->num_rows) response(_('No such user found.'),1);
   $row=$result->fetch_assoc();
   $email=$row["mail"];
   $subject = _('Email updated');
   $result=$db->query("UPDATE users SET mail = '$newemail'  WHERE userId='$userid'");
   $names=preg_split("/[\s,]+/",$username);
   $firstname=$names[0];
   $message=_('Hello').' '.$firstname.",\n\n".
   _('Your e-mail has been reset successfully.')."\n\n".
   _('Your new email is:')."\n".$newemail;
   sendEmail($newemail, $subject, $message);
   resendConfirmationEmail($newemail);
   response(_('Your e-mail has been reset successfully.').' '._('Check your email to confirm.') );
}

function login($number,$password)
{
   global $db,$systemURL,$countrycode;
   $number=$db->conn->real_escape_string(trim($number));
   $password=$db->conn->real_escape_string(trim($password));
   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   if ($number[0]=="0") $number=$countrycode.substr($number,1,strlen($number));
   $altnumber=$countrycode.$number;
   $result=$db->query("SELECT userId FROM users WHERE (number='$number' OR number='$altnumber') AND password=SHA2('$password',512)");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $userId=$row["userId"];
      $sessionId=hash('sha256',$userId.$number.time());
      $timeStamp=time()+86400*14; // 14 days to keep user logged in
      $result=$db->query("DELETE FROM sessions WHERE userId='$userId'");
      $result=$db->query("INSERT INTO sessions SET userId='$userId',sessionId='$sessionId',timeStamp='$timeStamp'");
      $db->conn->commit();
      setcookie("loguserid",$userId,time()+86400*14);
      setcookie("logsession",$sessionId,time()+86400*14);
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL);
      header("Connection: close");
      exit;
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."?error=1");
      header("Connection: close");
      exit;
      }
}
function logout()
{
   global $db,$systemURL;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("DELETE FROM sessions WHERE userId='$userid'");
      $db->conn->commit();
      }
   header("HTTP/1.1 301 Moved permanently");
   header("Location: ".$systemURL);
   header("Connection: close");
   exit;
}
function checkprivileges($userid)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $privileges=getprivileges($userid);
   if ($privileges<1)
      {
      response(_('Sorry, this command is only available for the privileged users.'),ERROR);
      exit;
      }
}
function smscode($number)
{
   global $db, $gatewayId, $gatewayKey, $gatewaySenderNumber, $connectors;
   $number=$db->conn->real_escape_string(trim($number));
   $smscheck=checktoomanysms();
   $smsipcheck=checktoomanysmsip();
      if ($smscheck===TRUE OR $smsipcheck===TRUE)
         {
         response(_('Our sms gateway is currently very busy please try again in 1 hour.'),ERROR);
         }
        if ($smscheck===FALSE)
        {
   srand();
   $number=normalizephonenumber($number);
   $number=$db->conn->real_escape_string($number);
   $userexists=0;
   $result=$db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows) $userexists=1;
   $smscode=rand(1000,9999);
   $smscodenormalized=str_replace(" ","",$smscode);
   $checkcode=md5("WB".$number.$smscodenormalized);
   if (!$userexists) $text=_('Enter this code to register:')." ".$smscode;
   else $text=_('Enter this code to change password:')." ".$smscode;
   $text=$db->conn->real_escape_string($text);
   if (!issmssystemenabled()) $result=$db->query("INSERT INTO sent SET number='$number',text='$text'");
   $result=$db->query("INSERT INTO history SET userId=0,bikeNum=0,action='REGISTER',parameter='$number;$smscodenormalized;$checkcode'");
   if (DEBUG===TRUE)
      {
      response($number,0,array("checkcode"=>$checkcode,"smscode"=>$smscode,"existing"=>$userexists));
      }
   else
      {
      sendSMS($number,$text);
      if (issmssystemenabled()==TRUE) response($number,0,array("checkcode"=>$checkcode,"existing"=>$userexists));
      else response($number,0,array("checkcode"=>$checkcode,"existing"=>$userexists));
      }
}
}
function trips($userId,$bike=0)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   $bike=$db->conn->real_escape_string(trim($bike));
   $bikeNum=intval($bike);
   if ($bikeNum)
      {
      $result=$db->query("SELECT longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum='$bikeNum' AND action='RETURN' ORDER BY time DESC");
      while($row = $result->fetch_assoc())
         {
         $jsoncontent[]=array("longitude"=>$row["longitude"],"latitude"=>$row["latitude"]);
         }
      }
   else
      {
      $result=$db->query("SELECT bikeNum,longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE action='RETURN' ORDER BY bikeNum,time DESC");
      $i=0;
      while($row = $result->fetch_assoc())
         {
         $bikenum=$row["bikeNum"];
         $jsoncontent[$bikenum][]=array("longitude"=>$row["longitude"],"latitude"=>$row["latitude"]);
         }
      }
   echo json_encode($jsoncontent); // TODO change to response function
}
function getuserlist()
{
   global $db;
   $result=$db->query("SELECT users.userId,username,mail,number,privileges,credit,userLimit FROM users LEFT JOIN credit ON users.userId=credit.userId LEFT JOIN limits ON users.userId=limits.userId ORDER BY username");
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=array("userid"=>$row["userId"],"username"=>$row["username"],"mail"=>$row["mail"],"number"=>$row["number"],"privileges"=>$row["privileges"],"credit"=>$row["credit"],"limit"=>$row["userLimit"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}
function getuserstats()
{
   global $db;
   $result=$db->query("SELECT users.userId,username,count(action) AS count FROM users LEFT JOIN history ON users.userId=history.userId WHERE history.userId IS NOT NULL GROUP BY username ORDER BY count DESC");
   while($row = $result->fetch_assoc())
      {
      $result2=$db->query("SELECT count(action) AS rentals FROM history WHERE action='RENT' AND userId=".$row["userId"]);
      $row2=$result2->fetch_assoc();
      $result2=$db->query("SELECT count(action) AS returns FROM history WHERE action='RETURN' AND userId=".$row["userId"]);
      $row3=$result2->fetch_assoc();
      $jsoncontent[]=array("userid"=>$row["userId"],"username"=>$row["username"],"count"=>$row["count"],"rentals"=>$row2["rentals"],"returns"=>$row3["returns"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}
function getusagestats()
{
   global $db;
   $result=$db->query("SELECT count(action) AS count,DATE(time) AS day,action FROM history WHERE userId IS NOT NULL AND action IN ('RENT','RETURN') GROUP BY day,action ORDER BY day DESC LIMIT 60");
   while($row=$result->fetch_assoc())
      {
      $jsoncontent[]=array("day"=>$row["day"],"count"=>$row["count"],"action"=>$row["action"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}
function edituser($userid)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $result=$db->query("SELECT users.userId,userName,mail,number,privileges,userLimit,credit FROM users LEFT JOIN limits ON users.userId=limits.userId LEFT JOIN credit ON users.userId=credit.userId WHERE users.userId=".$userid);
   $row=$result->fetch_assoc();
   $jsoncontent=array("userid"=>$row["userId"],"username"=>$row["userName"],"email"=>$row["mail"],"phone"=>$row["number"],"privileges"=>$row["privileges"],"limit"=>$row["userLimit"],"credit"=>$row["credit"]);
   echo json_encode($jsoncontent);// TODO change to response function
}
function saveuser($userid,$username,$email,$phone,$privileges,$limit)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $username=$db->conn->real_escape_string(trim($username));
   $email=$db->conn->real_escape_string(trim($email));
   $phone=$db->conn->real_escape_string(trim($phone));
   $privileges=$db->conn->real_escape_string(trim($privileges));
   $limit=$db->conn->real_escape_string(trim($limit));
   $result=$db->query("UPDATE users SET username='$username',mail='$email',privileges='$privileges' WHERE userId=".$userid);
   if ($phone) $result=$db->query("UPDATE users SET number='$phone' WHERE userId=".$userid);
   $result=$db->query("UPDATE limits SET userLimit='$limit' WHERE userId=".$userid);
   response(_('Details of user')." ".$username." "._('updated').".");
}
function addcredit($userid,$creditmultiplier)
{
   global $db, $credit;
   $userid=$db->conn->real_escape_string(trim($userid));
   $creditmultiplier=$db->conn->real_escape_string(trim($creditmultiplier));
   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
   $addcreditamount=$requiredcredit*$creditmultiplier;
   $result=$db->query("UPDATE credit SET credit=credit+".$addcreditamount." WHERE userId=".$userid);
   $result=$db->query("INSERT INTO history SET userId='$userid',action='CREDITCHANGE',parameter='".$addcreditamount."|add+".$addcreditamount."'");
   $result=$db->query("SELECT userName FROM users WHERE users.userId=".$userid);
   $row=$result->fetch_assoc();
   response(_('Added')." ".$addcreditamount.$credit["currency"]." "._('credit for')." ".$row["userName"].".");
}
function addcreditmollie($userid,$addcreditamount)
{

}
function addcreditmandate($userid)
{

}
function resetpassword($number)
{
   global $db, $systemname, $systemrules, $systemURL;
   $number=$db->conn->real_escape_string(trim($number));
   $result=$db->query("SELECT mail,userName FROM users WHERE number='$number'");
   if (!$result->num_rows) response(_('No such user found.'),1);
   $row=$result->fetch_assoc();
   $email=$row["mail"];
   $username=$row["userName"];
   $subject = _('Password reset');
   mt_srand(crc32(microtime()));
   $password=substr(md5(mt_rand().microtime().$email),0,8);
   $result=$db->query("UPDATE users SET password=SHA2('$password',512) WHERE number='".$number."'");
   $names=preg_split("/[\s,]+/",$username);
   $firstname=$names[0];
   $message=_('Hello').' '.$firstname.",\n\n".
   _('Your password has been reset successfully.')."\n\n".
   _('Your new password is:')."\n".$password;
   sendEmail($email, $subject, $message);
   response(_('Your password has been reset successfully.').' '._('Check your email.'));
}
function mapgetmarkers($userid)
{
   global $db;

   $jsoncontent=array();
  // $result=$db->query("SELECT standId,  COALESCE(sum(shared!=1),0) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId LEFT JOIN sharing on bikes.bikeNum=sharing.bikeNum WHERE stands.serviceTag=0 GROUP BY standName ORDER BY standName");
  // AND 1234 IN(sharing.phone1, sharing.phone2)
  
   $userid = $db->conn->real_escape_string(trim($userid));
   $userphoned = getphonenumber($userid);
   if ($userphoned == FALSE) $userphoned="0612345678";
   $result=$db->query("SELECT standId,  COALESCE(sum(shared=0 or $userphoned IN(sharing.phone1, sharing.phone2, sharing.phone3)),0) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId LEFT JOIN sharing on bikes.bikeNum=sharing.bikeNum WHERE stands.serviceTag=0 GROUP BY standName ORDER BY standName");
   
   
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=$row;
      }
   echo json_encode($jsoncontent); // TODO proper response function
}
function mapgetlimit($userId)
{
   global $db;
   $userId=$db->conn->real_escape_string(trim($userId));
   if (!isloggedin()) response("");
   $result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser='$userId'");
   $row = $result->fetch_assoc();
   $rented= $row["countRented"];
   $result=$db->query("SELECT userLimit FROM limits where userId='$userId'");
   $row = $result->fetch_assoc();
   $limit = $row["userLimit"];
   $currentlimit=$limit-$rented;
   $usercredit=0;
   $usercredit=getusercredit($userId);
   echo json_encode(array("limit"=>$currentlimit,"rented"=>$rented,"usercredit"=>$usercredit));
}
function mapgeolocation ($userid,$lat,$long)
{
   global $db;
   $userid=$db->conn->real_escape_string(trim($userid));
   $lat=$db->conn->real_escape_string(trim($lat));
   $long=$db->conn->real_escape_string(trim($long));
   $result=$db->query("INSERT INTO geolocation SET userId='$userid',latitude='$lat',longitude='$long'");
   response("");
}

function checktoomanysms()
{
   global $db,$watches;
   $jsoncontent=array();
    $currenttime=date("Y-m-d H:i:s",time()-1*3600);
    $result=$db->query("SELECT number,text FROM sent WHERE text LIKE '%Enter%' AND time>'$currenttime'");
    $result2=$db->query("SELECT number,text FROM sent WHERE text LIKE '%Voer%' AND time>'$currenttime'");
    
   if ($result->num_rows>=30 OR $result2->num_rows>=30)
   {
        return TRUE;
        
   }
   else return FALSE;
   
}

function checktoomanysmsip()
{
   global $db,$watches;
   $jsoncontent=array();
    $currenttime=date("Y-m-d H:i:s",time()-1*3600);
    $result=$db->query("SELECT sms_text,IP FROM received WHERE sms_text LIKE '%command.php?action=smscode%' AND time>'$currenttime' AND IP='".$_SERVER['REMOTE_ADDR']."'");
   // while($row = $result->fetch_assoc())
  //    {
   //   $jsoncontent[]=$row;
 //     }
 //  echo json_encode($jsoncontent);
   if ($result->num_rows>30)
   
   {
        echo 'test';
        return TRUE;
        
   }
   else return FALSE;
   
}


function addmollieid($userid, $string)
{

}

function setlimittoone($userid)
{
   global $db, $credit;
   $db->query("UPDATE limits SET userLimit=1 WHERE userId='$userid'");
   // response('Succes');
}

function locked($type,$userId,$bikeNum)
{
    global $db;
    $bikeNum = intval($bikeNum);
    if ($type==1)
    {
    $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser='$userId' AND bikeNum='$bikeNum' ORDER BY bikeNum");
    $bikelist=$result->num_rows;
    if ($bikelist==0 and $type==1) response("Bike not rented");
    }
   $result=$db->query("SELECT bikelock FROM bikes WHERE bikeNum='$bikeNum'");
   $row=$result->fetch_assoc();
   $lockid=$row["bikelock"];
    
    
    $curl = curl_init();

$lockid = 1;

curl_setopt_array($curl, array(
  CURLOPT_URL => "",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\"id\":1,\"attributes\":{\"data\":\"UNLOCK#\"},\"deviceId\":$lockid,\"type\":\"custom\",\"textChannel\":false,\"description\":\"unlock\"}",
  CURLOPT_HTTPHEADER => array(
    "authorization: ",
    "cache-control: no-cache",
    "content-type: application/json",
    "postman-token:"
  ),
));

if ($type==1) response("Opening lock");

}

function lock($type,$userId,$bikeNum)
{
    
global $db;
$bikeNum = intval($bikeNum);

if ($type==1)
    {
    $bikeNum=$db->conn->real_escape_string(trim($bikeNum));
   $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser='$userId' AND bikeNum='$bikeNum' ORDER BY bikeNum");
    $bikelist=$result->num_rows;
    if ($bikelist==0) response("Bike not rented");
    }

$result=$db->query("SELECT bikelock FROM bikes WHERE bikeNum='$bikeNum'");
$row=$result->fetch_assoc();
$lockid=$row["bikelock"];

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://188.166.83.181/api/commands/send",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\"id\":1,\"attributes\":{\"data\":\"UNLOCK#\"},\"deviceId\":$lockid,\"type\":\"custom\",\"textChannel\":false,\"description\":\"unlock\"}",
  CURLOPT_HTTPHEADER => array(
    "authorization: Basic YWRtaW46YWRtaW4=",
    "cache-control: no-cache",
    "content-type: application/json",
    "postman-token: 1742bafa-05d7-fcfa-e35e-81f30a27e099"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($type==1) response("Opening lock");

}

// TODO for admins: show bikes position on map depending on the user (allowed) geolocation, do not display user bikes without geoloc
?>