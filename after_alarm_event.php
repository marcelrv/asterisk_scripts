#!/usr/bin/php -q
<?php
/*
Simple Alarm call center script. This script can by used as after script for Asterisks AlarmReceiver cmd.

This script read saved events from asterisk alarm receiver. If the event is important (Alarm event) script create
asterisks call file which make a call and play sound message to selected numbers.

You can also receive formated events to email.

Notes:

For better results in communication you can try increase output gain on your ATA (default setting was -3)

        FXS Port Output Gain: +1

You must set all other regional settings to match your alarm !!! (FXS Port Impedance, Ring Frequency, Ring Voltage, ...)

Other info for Asterisks AlarmReceiver cmd can be found here:
http://www.voip-info.org/wiki/index.php?page=Asterisk+cmd+AlarmReceiver

------------------------------------------------------------------------------------------------
Example: alarmreceiver.conf

[general]
timestampformat = %a %b %d, %Y @ %H:%M:%S %Z
eventcmd = /usr/local/bin/after_alarm_event.php
eventspooldir = /var/spool/asterisk/alarm_events
logindividualevents = yes
fdtimeout = 2000
sdtimeout = 200
loudness = 8192
------------------------------------------------------------------------------------------------

Example: sip.conf (pstn to sip part)

[31]
type=friend
context=phones
host=dynamic
secret=*******
callerid="Ademco Alarm" <31>
dtmfmode=inband
disallow=all
allow=ulaw

------------------------------------------------------------------------------------------------
Example: extension.conf

[internal]
;alarm receiver
exten => 560,1,Verbose(1|Extension 560 - Alarm Receiver)
exten => 560,n,Ringing()
exten => 560,n,Wait(2)
exten => 560,n,AlarmReceiver()
exten => 560,n,Hangup()

[alarmreport]
exten => start,1,Answer()
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(ha/alarm)
exten => start,n,Wait(1)
exten => start,n,Playback(vm-goodbye)
exten => start,n,Hangup()


------------------------------------------------------------------------------------------------

Written by
Uros Indihar <uros.indihar@alphito.si>
Alphito d.o.o.

Changelog
        0.1 Initial releasex
        0.2 1.12.1008
                Added mail support

Updated by Marcel Verpaalen              
        0.3 Added alarm codes
        0.4 Added MQTT link
        0.5 Added whatsApp messages
        0.6 Added SMS sending via dongle

License GNU GPL2.

Warranty: None. Use at your own risk !

*/
class ademcoEventParser {
        var $zoneCodes=array(
        1 => "Detector Entree Kelder",
        2 => "Detector Keuken",
        3 => "Detector Deur Centrale",
        16 => "Detector Entree Voordeur",
        17 => "Detector Kantoor",
        18 => "Detector Woonkamer Voor",
        19 => "Detector Woonkamer Achter",
        20 => "Detector Overloop",
        21 => "Rookmelder Meterkast",
        22 => "Rookmelder Overloop",
        );

        var $eventQualifier=array(
        1 => "New Event or Opening",
        3 => "New Restore or Closing",
        6 => "Previously reported condition still present (Status report)",
        );
        var $eventCategoryList=array(
        10 => "Medisch Alarm",
        11 => "Brand Alarm",
        12 => "Paniek Alarm",
        13 => "Inbraak Alarm",
        14 => "Algemeen alarm",
        20 => "FIRE SUPERVISORY",
        30 => "SYSTEM TROUBLES",
        32 => "SOUNDER/RELAY TROUBLES",
        33 => "SYSTEM PERIPHERAL TROUBLES",
        35 => "COMMUNICATION TROUBLES",
        37 => "PROTECTION LOOP",
        38 => "SENSOR",
        40 => "OPEN/CLOSE",
        41 => "REMOTE ACCESS",
        42 => "ACCESS CONTROL",
        50 => "SYSTEM DISABLES",
        52 => "SOUNDER/RELAY DISABLES",
        53 => "SYSTEM PERIPHERAL DISABLES",
        55 => "COMMUNICATION DISABLES",
        57 => "BYPASSES",
        60 => "TEST / MISC",
        61 => "TEST / MISC",
        62 => "EVENT LOG",
        63 => "SCHEDULING",
        64 => "PERSONNEL MONITORING",
        65 => "SPECIAL CODES",
        75 => "Protection One non-standard",
        76 => "Protection One non-standard",
        77 => "Protection One non-standard",
        78 => "Protection One non-standard",
        90 => "Remote - Download End",
        99 => "Other",
        );
        var $eventMap=array(
        100 => "Medical Emergcy",
        101 => "Pendant Transmitter ",
        102 => "Fail to report in ",
        110 => "Fire Alarm Silenced Event",
        111 => "SMOKE Fire w/VERIFICATION ",
        112 => "Combustion Fire",
        113 => "WATERFLOW Fire",
        114 => "Heat Fire",
        115 => "Pull Station Fire",
        116 => "Duct Fire",
        117 => "Flame Fire",
        118 => "Near Alarm Fire",
        120 => "Panic Alarm Panic",
        121 => "DURESS Panic",
        122 => "SILENT Panic",
        123 => "AUDIBLE Panic",
        124 => "Duress-Access Granted Panic",
        125 => "Duress-Egress Granted Panic",
        130 => "Burglary Alarm",
        131 => "PERIMETER Burg",
        132 => "INTERIOR Burg",
        133 => "24 HR BURG (AUX) Burg",
        134 => "ENTRY/EXIT Burg",
        135 => "DAY/NIGHT Burg",
        136 => "Outdoor Burg",
        137 => "TAMPER Burg",
        138 => "Near Alarm Burg",
        139 => "Intrusion Verifier Burg",
        140 => "General Alarm Alarm",
        141 => "Polling Loop Open Alarm",
        142 => "POLLING LOOP SHORT (AL) Alarm",
        143 => "EXPANSION MOD FAILURE Alarm",
        144 => "Sensor Tamper Alarm",
        145 => "Expansion Module Tamper Alarm",
        146 => "SILENT BURG Burg",
        147 => "Sensor Supervision Trouble - Sensor Super.  #",
        150 => "24 HOUR (AUXILIARY) Alarm",
        151 => "Gas Detected Alarm",
        152 => "Refrigeration Alarm",
        153 => "Loss of Heat Alarm",
        154 => "Water Leakage Alarm",
        155 => "Foil Break ",
        156 => "Day Trouble ",
        157 => "Low Bottled Gas Level Alarm",
        158 => "High Temp Alarm",
        159 => "Low Temp Alarm",
        161 => "Loss of Air Flow Alarm",
        162 => "Carbon Monoxide Detected Alarm",
        163 => "Tank Level ",
        168 => "High Humidity ",
        169 => "Low Humidity ",
        200 => "FIRE SUPERVISORY Super.",
        201 => "Low Water Pressure Super",
        202 => "Low CO2 Super.",
        203 => "Gate Valve Sensor Super.",
        204 => "Low Water Level Super.",
        205 => "Pump Activated Super.",
        206 => "Pump Failure Super.",
        24  => "OUR NON",
        300 => "System Trouble ",
        301 => "AC LOSS ",
        302 => "LOW SYSTEM BATT ",
        303 => "RAM Checksum Bad ",
        304 => "ROM Checksum Bad ",
        305 => "SYSTEM RESET ",
        306 => "PANEL PROG CHANGE ",
        307 => "Self-Test Failure ",
        308 => "System Shutdown ",
        309 => "Battery Test Fail ",
        310 => "GROUND FAULT ",
        311 => "Battery Missing ",
        312 => "Power Supply Overcurent ",
        313 => "Engineer Reset Status",
        314 => "Primary Power Supply Failure Trouble - Pri Pwr Supply Fail ",
        316 => "System Tamper Trouble - APL System trouble ",
        320 => "SOUNDER / RELAY ",
        321 => "BELL 1 ",
        322 => "BELL 2 ",
        323 => "Alarm Relay ",
        324 => "Trouble Relay ",
        325 => "Reversing Relay ",
        326 => "Notification Appliance Ckt.  # 3 ",
        327 => "Notification Appliance Ckt.  # 4 ",
        331 => "Polling Loop Open ",
        332 => "POLLING LOOP SHORT ",
        333 => "Exp. Module Failure (353) ",
        334 => "Repeater Failure ",
        335 => "Local Printer Paper Out ",
        336 => "Local Printer Failure ",
        337 => "EXP. MOD. DC LOSS ",
        338 => "EXP. MOD. LOW BAT ",
        339 => "EXP. MOD. RESET ",
        341 => "EXP. MOD. TAMPER ",
        342 => "Exp. Module AC Loss ",
        343 => "Exp. Module Self Test Fail ",
        344 => "RF Rcvr Jam Detect  # ",
        345 => "AES Encryption disabled/enabled ",
        350 => "Communication ",
        351 => "TELCO 1 FAULT ",
        352 => "TELCO 2 FAULT ",
        353 => "LR Radion Xmitter Fault (333) ",
        354 => "FAILURE TO COMMUNICATE ",
        355 => "Loss of Radio Super. (R330) ",
        356 => "Loss of Central Polling ",
        357 => "LRR XMTR. VSWR ",
        370 => "Protection Loop ",
        371 => "Protection Loop Open ",
        372 => "Protection Loop Short ",
        373 => "FIRE TROUBLE ",
        374 => "EXIT ERROR (BY USER) Alarm",
        375 => "Panic Zone Trouble ",
        376 => "Hold-Up Zone Trouble ",
        377 => "Swinger Trouble Trouble - Swinger Trouble #",
        378 => "Cross-zone Trouble Trouble ",
        380 => "SENSOR TRBL - GLOBAL ",
        381 => "LOSS OF SUPERVISION ",
        382 => "LOSS OF SUPRVSN ",
        383 => "SENSOR TAMPER ",
        384 => "RF LOW BATTERY ",
        385 => "SMOKE HI SENS. ",
        386 => "SMOKE LO SENS. ",
        387 => "INTRUSION HI SENS. ",
        388 => "INTRUSION LO SENS. ",
        389 => "DET. SELF TEST FAIL ",
        391 => "Sensor Watch Failure ",
        392 => "Drift Comp. Error ",
        393 => "Maintenance Alert ",
        400 => "Open/Close Opening/Closing E = Open, R = Close",
        401 => "OPEN/CLOSE BY USER Opening",
        402 => "Group O/C Closing",
        403 => "AUTOMATIC OPEN/CLOSE Opening",
        404 => "Late to O/C Opening",
        405 => "Deferred O/C Event & Restore Not Applicable",
        406 => "CANCEL (BY USER) Opening",
        407 => "REMOTE ARM/DISARM Opening",
        408 => "QUICK ARM Event Not Applicable for opening / Closing",
        409 => "KEYSWITCH OPEN/CLOSE Opening",
        411 => "CALLBACK REQUESTED Remote",
        412 => "Success-Download/access Remote",
        413 => "Unsuccessful Access Remote",
        414 => "System Shutdown Remote",
        415 => "Dialer Shutdown Remote",
        416 => "Successful Upload Remote",
        421 => "Access Denied Access",
        422 => "Access Report by User Access",
        423 => "Forced Access Panic",
        424 => "Egress Denied Access",
        425 => "Egress Granted Access",
        426 => "Access Door Propped Open Access",
        427 => "Access Point DSM Trouble Access",
        428 => "Access Point RTE Trouble Access",
        429 => "Access Program Mode Entry Access",
        430 => "Access Program Mode Exit Access",
        431 => "Access Threat Level Change Access",
        432 => "Access Relay/Trigger Fail Access",
        433 => "Access RTE Shunt Access",
        434 => "Access DSM Shunt Access",
        435 => "Second Person Access ACCESS - User  #",
        436 => "Irregular Access ACCESS - Irregular Access - User  #",
        441 => "Armed Stay Opening",
        442 => "Keyswitch Armed Stay Opening",
        450 => "Exception O/C Opening",
        451 => "Early O/C Opening",
        452 => "Late O/C Opening",
        453 => "Failed to Open ",
        454 => "Failed to Close ",
        455 => "Auto-Arm Failed ",
        456 => "Partial Arm Closing",
        457 => "Exit Error (User) Closing",
        458 => "User on Premises Opening",
        459 => "Recent Close ",
        461 => "Wrong Code Entry Access - Wrong Code entry (Restore not applicable)",
        462 => "Legal Code Entry Acces",
        463 => "Re-arm after Alarm Status",
        464 => "Auto Arm Time Extended Status",
        465 => "Panic Alarm Reset Status",
        466 => "Service On/Off Premises Access - Service on Prem - User  #",
        501 => "Access Reader Disable Disable",
        520 => "Sounder/Relay Disable Disable",
        521 => "Bell 1 Disable Disable",
        522 => "Bell 2 Disable Disable",
        523 => "Alarm Relay Disable Disable",
        524 => "Trouble Relay Disable Disable",
        525 => "Reversing Relay Disable Disable",
        526 => "Notification Appliance Ckt  # 3 Disable",
        527 => "Notification Appliance Ckt  # 4 Disable",
        531 => "Module Added Super.",
        532 => "Module Removed Super.",
        551 => "Dialer Disabled Disable",
        552 => "Radio Xmitter Disabled Disable",
        553 => "Remote Upload/Download Disable",
        570 => "ZONE/SENSOR BYPASS Bypass",
        571 => "Fire Bypass Bypass",
        572 => "24 Hour Zone Bypass Bypass",
        573 => "Burg. Bypass Bypass",
        574 => "Group Bypass Bypass",
        575 => "SWINGER BYPASS Bypass",
        576 => "Access Zone Shunt Access",
        577 => "Access Point Bypass Access",
        578 => "Zone Bypass Bypass - Vault Bypass ",
        579 => "Zone Bypass Bypass - Vent Zone Bypass ",
        601 => "MANUAL TEST Test",
        602 => "PERIODIC TEST Test",
        603 => "Periodic RF Xmission Test",
        604 => "FIRE TEST Test",
        605 => "Status Report To Follow Test",
        606 => "LISTEN-IN TO FOLLOW Listen",
        607 => "WALK-TEST MODE Test",
        608 => "System Trouble Present Test",
        609 => "VIDEO XMTR ACTIVE Listen",
        611 => "POINT TESTED OK Test",
        612 => "POINT NOT TESTED Test",
        613 => "Intrusion Zone Walk Tested Test",
        614 => "Fire Zone Walk Tested Test",
        615 => "Panic Zone Walk Tested Test",
        616 => "Service Request ",
        621 => "EVENT LOG RESET ",
        622 => "EVENT LOG 50% FULL ",
        623 => "EVENT LOG 90% FULL ",
        624 => "EVENT LOG OVERFLOW ",
        625 => "TIME/DATE RESET ",
        626 => "TIME/DATE INACCURATE ",
        627 => "PROGRAM MODE ENTRY ",
        628 => "PROGRAM MODE EXIT ",
        630 => "Schedule Change ",
        631 => "Exception Sched. Change ",
        632 => "Access Schedule Change ",
        641 => "Senior Watch Trouble ",
        642 => "Latch-key Supervision Status",
        651 => "Code sent to Identify the control panel as an ADT Authorized Dealer.",
        654 => "System Inactivity Trouble - System Inactivity",
        900 => "Download Abort Remote - Download Abort (Restore not applicable)",
        901 => "Download Start/End Remote - Download Start ",
        902 => "Download Interrupted Remote - Download Interrupt ",
        910 => "Auto-Close with Bypass Closing - Auto Close - Bypass ",
        911 => "Bypass Closing Closing - Bypass Closing ",
        912 => "Fire Alarm Silenced Event",
        913 => "Supervisory Point test Start/End Event ",
        914 => "Hold-up test Start/End Event ",
        915 => "Burg. Test Print Start/End Event",
        916 => "Supervisory Test Print Start/End Event",
        917 => "Burg. Diagnostics Start/End Event",
        918 => "Fire Diagnostics Start/End Event",
        919 => "Untyped diagnostics Event",
        920 => "Trouble Closing (closed with burg. during exit)",
        921 => "Access Denied Code Unknown Event",
        922 => "Supervisory Point Alarm Alarm - Zone  #",
        923 => "Supervisory Point Bypass Event - Zone  #",
        924 => "Supervisory Point Trouble Trouble - Zone  #",
        925 => "Hold up Point Bypass Event - Zone  #",
        926 => "AC Failure for 4 hours Event",
        927 => "Output Trouble ",
        928 => "User code for event Event",
        929 => "Log-off Event",
        954 => "CS Connection Failure Event",
        961 => "Rcvr Database Connection Fail/Restore",
        962 => "License Expiration Notify Event",
        999 => "1 and 1/3 DAY NO READ LOG EVENT LOG ONLY, No report to CS.",
        );
        var $eventsDir="/var/spool/asterisk/alarm_events/";
        var $eventsLogdir="/var/log/asterisk/alarm_events/";
        var $eventsLogFile="alarm.log";
        var $eventPrefix="event-";
        var $lastEventSave="last_received_event";
        var $actionChannels=array( //ademco_id => array(channel1,channel2,...);
        "0019"=>array(
        "SIP/30",
        //"SIP/20 (Asterisk channel)",
        ),
        "1234"=>array(
        "local/06XXXXXXXX@from-internal",
        ),
        );
        var $actionChannelsSMS=array( //ademco_id => array(channel1,channel2,...);
        "0019"=>array(
        ),
        "1234"=>array(
        "+316XXXXXXXX"
        ),
        );

        var $callFileDir="/var/spool/asterisk/outgoing/";

        var $actionChannelsMail=array( //ademco_id => array(Email1=>Name1,Email2=>Name2,...);
        "0019"=>array(
        //"your.email@provider.com"=>"Your Name",
        "2ne.email@gmail.com"=>"Your 2nd Name",
        ),
        "1234"=>array(
        "email@gmail.com"=>"Email for 2nd code",
        ),
        );
        var $emailFromName="Ademco Alarm Report";
        var $emailFrom="sender@email.com";

        var $callerId="Alarm Report <80>";

        function setEventsDir($dir) {
                $this->eventsDir=$dir;
        }

        function getEventFiles() {
                $eventFiles=array();
                $eventPrefixLen=strlen($this->eventPrefix);
                $dh = opendir($this->eventsDir);
                while (($file = readdir($dh)) !== false) {
                        if (substr($file,0,$eventPrefixLen) == $this->eventPrefix) {
                                $eventFiles[$file]=filemtime($this->eventsDir.$file);
                        }
                }
                asort($eventFiles);
                closedir($dh);
                $this->eventFiles=$eventFiles;
        }
        function eventDescription($e) {
                $evDescription="";
                if(array_key_exists( $e["e"] , $this->eventMap) )
                $evDescription=$this->eventMap[$e["e"]];
                else
                $evDescription="Unknown Alarmcode (". $e["e"] . ")";
                return $evDescription   ;
        }
        function zoneCodesDescription($e) {
                $zoneDescription="";
//              print_r(intval($e["s"]));
                if(array_key_exists( intval($e["s"]) , $this->zoneCodes) )
                $zoneDescription=$this->zoneCodes[intval($e["s"])];
                else
                $zoneDescription="Unknown Zone";
                $zoneDescription .=" (". $e["s"] . ")";
                return $zoneDescription         ;
        }

        function eventCategory($e) {
                $evCategory="";
                $ev=substr($e["e"],0,2);
                if(array_key_exists( $ev , $this->eventCategoryList) )
                $evCategory=$this->eventCategoryList[$ev];
                else
                $evCategory="Unknown eventCategory (". $ev . ")";
                return $evCategory      ;
        }

        function loop() {
                foreach ($this->eventFiles as $file => $mtime) {
                        $data=$this->parse($file);
                        foreach ($data["events"] as $event) {
                                //echo $event;
                                $e=$this->parseEvent($event);
//                              if ($this->getSavedEvent() == $event) {
                                        //event is duplicated to last event
//                                      $this->log($data["metadata"],$event,"Duplicated event",$e);
//                              } else {
                                        $this->saveEvent($event);
                                        $this->log($data["metadata"],$event,"New event",$e);
                                        $this->action($e,$data["metadata"],$event);
//                              }

                        }
                        // print_r($data);
                        //remove file
                        //unlink($this->eventsDir.$file);
                        rename($this->eventsDir.$file,$this->eventsLogdir . $file);

                }
        }

        function action($e,$metadata,$event) {

                        $msg =$this->eventCategory($e)."\n\n";
                        $msg.="Details\n";
                        $msg.="Type : ";
                        $msg.=$this->eventDescription($e)." (". $e["e"] . ")\n";
                        $msg.="Time : ".date("d.m.Y H:i:s",time()).".\n";
                        $msg.="Message Type: ";
                        $msg.=$this->eventQualifier[$e["q"]]."\n";
                        $msg.="Sensor: " ;
                        $msg.=$this->zoneCodesDescription($e)."\n";
                        $msg.="Group: " . $e["g"] . "\n";

                        $sms =$this->eventCategory($e)." - ";
                        $sms.=$this->eventDescription($e)." (". $e["e"] . ")\n";
                        $sms.="Time : ".date("d.m.Y H:i:s",time()).".\n";
                        $sms.="Sensor: " ;
                        $sms.=$this->zoneCodesDescription($e)."\n";

                        $techmsg="Ademco alarm report technical data.\n\n";
                        $techmsg.="Logged event at ".date("d.m.Y H:i:s",time()).".\n";
                        $techmsg.=implode(", ",$metadata)."\n";
                        $techmsg.=$event."\n";
                        $techmsg.=implode(", ",$e)."\n";


        if((intval($e["e"]) < 200) && (intval($e["q"]) == 1) ){
                        //do action on alarm events
                        $this->log($metadata,$event,"Starting action",$e);
                        foreach ($this->actionChannels[$e["id"]] as $callChannel) {
                                $this->createCallFile($callChannel);
                        }
                        foreach ($this->actionChannelsSMS[$e["id"]] as $SMSnr) {
                                $this->createSMSFile($SMSnr,$e);
                        }

                }

                //send email report but not for door open/close
                if (is_array($this->actionChannelsMail[$e["id"]]) AND substr($e["e"],0,2) !="40") {
                                $subject ="[". $this->eventCategory($e) . "] ".$this->eventDescription($e);
                        foreach ($this->actionChannelsMail[$e["id"]] as $email => $name) {
                                $this->sendMail($subject,$name,$email,$this->emailFromName,$this->emailFrom,$msg . "\n". $techmsg. "\nSMS:\n".$sms);
                        }
                }
        }

        function createCallFile($channel) {
                $callFile ="Channel: ".$channel."\n";
                $callFile.="CallerID: ".$this->callerId."\n";
                $callFile.="MaxRetries: 3\n";
                $callFile.="RetryTime: 60\n";
                $callFile.="WaitTime: 30\n";
                //              $callFile.="Context: alarmreport\n";
                //              $callFile.="Extension: start\n";
                $callFile.="Context: outboundmsg1\n";
                $callFile.="Extension: s\n";
                $callFile.="Priority: 1\n";
                file_put_contents($this->callFileDir.uniqid("alarm").".call",$callFile);
        }

        function createSMSFile($GSMnr,$e) {
                $sms =$this->eventCategory($e)." - ";
                $sms.=$this->eventDescription($e)." (". $e["e"] . ") ";
                $sms.="Time : ".date("d.m.Y H:i:s",time()).". ";
                $sms.="Sensor: " ;
                $sms.=$this->zoneCodesDescription($e)."";

                /*
                $callFile ="Channel: local/111@from-internal\n";
                // $callFile.="CallerID: ".$this->callerId."\n";
                $callFile.="Application: DongleSendSMS\n";
                $callFile.='Data: dongle0,'.$GSMnr.',"'.$sms.'"\n';
                file_put_contents($this->callFileDir.uniqid("alarm").".sms.",$callFile);
                */
                $smscmd = 'dongle sms dongle0 '. $GSMnr . ' " ' .$sms. ' " ' ;
                exec ("asterisk -rx '".$smscmd. "'");
                }
        function getSavedEvent () {
                $lastEvent=@file_get_contents($this->eventsDir.$this->lastEventSave);

                return $lastEvent;
        }

        function saveEvent($event) {
                file_put_contents($this->eventsDir.$this->lastEventSave,$event);
        }

        function parseEvent($event) {
                $e=array(
                "id" => substr($event,0,4),
                "msg_type" => substr($event,4,2),
                "q" => substr($event,6,1),
                "e" => substr($event,7,3),
                "g" => substr($event,10,2),
                "s" => substr($event,12,-1),
                "z" => substr($event,10,-1),

                );
//              print_r($e);

                return($e);
        }

        function slog($metadata,$event,$string,$e) {
                syslog(LOG_NOTICE,implode(", ",$metadata).", ".$event.", ".$string.", ".implode(", ",$e).", ".$this->eventDescription($e));
        }
        function log($metadata,$event,$string,$e) {
                $logLine=implode(", ",$metadata).", ".$event.", ".$string.", ".implode(", ",$e).", ". $this->eventDescription($e)."\n";
                file_put_contents($this->eventsLogdir.$this->eventsLogFile,$logLine,FILE_APPEND);
        }

        function parse($file) {
                $fp=fopen($this->eventsDir.$file,"r");
                $section="";
                $data=array();
                while (!feof($fp)) {
                        $line = trim(fgets($fp,4096));
                        if (substr($line,0,1) == "[") {
                                //new section started
                                $section=substr($line,1,-1);
                                $data[$section]=array();
                        } elseif (strlen($section) > 0) {
                                //we are in section
                                switch ($section) {
                                case "metadata":
                                        if (strlen($line) > 0) {
                                                //dont include empty lines
                                                list($key,$val)=explode("=",$line);
                                                //echo $key."->".$val."\n";
                                                $data[$section][trim($key)]=trim($val);
                                        }
                                        break;
                                case "events":
                                        if (strlen($line) > 0) {
                                                $data[$section][]=trim($line);
                                        }
                                        break;
                                }
                        }
                        //echo $line."\n";
                }
                fclose($fp);

                return $data;
        }

        function sendMail($subject,$tostr,$toemail,$fromstr,$fromemail,$msg) {
                $from = '"';
                $from.=$this->encode($fromstr);
                $from.= '"';
                $from.="<".$fromemail.">";

                $to = '"';
                $to.=$this->encode($tostr);
                $to.= '"';
                $to.="<".$toemail.">";

                //$msubject=$this->encode($subject);
                $msubject=$subject;

                $headers  = "MIME-Version: 1.0\n";
                $headers .= "Content-type: text/plain; charset=utf-8\n";
                $headers .= "From: ".$from."\n";

                return mail($to, $msubject, $msg, $headers);
        }

        function encode($in_str, $charset="UTF-8") {
                $out_str = $in_str;
                if ($out_str && $charset) {

                        $end = "?=";
                        $start = "=?" . $charset . "?B?";
                        $spacer = $end . "\n " . $start;

                        $length = 75 - strlen($start) - strlen($end);
                        $length = floor($length/2) * 2;

                        $out_str = base64_encode($out_str);
                        $out_str = chunk_split($out_str, $length, $spacer);

                        $spacer = preg_quote($spacer);
                        $out_str = preg_replace("/" . $spacer . "$/", "", $out_str);
                        $out_str = $start . $out_str . $end;
                }

                return $out_str;
        }

}

$aep=new ademcoEventParser();
$aep->getEventFiles();
$aep->loop();

?>
