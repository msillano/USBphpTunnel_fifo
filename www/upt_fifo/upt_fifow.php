<?php
$f=dirname(__FILE__);
require_once("$f/../remoteDB/irp_commonSQL.php");
require_once ("$f/upt_fifo.php");
     
// polling: tests if commands in fifo
// sends request to arduino 
//echo "* W received by server\n";
$next = peekNextRequest();       // new, in WAIT siate
if ($next == NULL) {
// ============= repeat last comand NOT processed OK
  $record = peekLastRequest();   // old, in PROCESS status, missed
  if ($record == NULL) exit;     // no old commands 
  $value = $record['value'];
  $data = $record['data'];
  if ($record['type'] == 'GET'){
       echo"G$value\n";      // to ARDUINO
  }
  if ($record['type'] == 'SET'){
       echo"S$value$data\n"; // to ARDUINO
   }
  exit;
}
// ======================= new
if ($next == 'GET') {        // get waiting
   $value = peekGETrequest();
   echo"G$value\n";  // to ARDUINO
   exit;   
   }
 if ($next == 'SET') {        // set  waiting
   $req = peekSETrequest();
   $value = $req[1];
   $data = $req[2];
   echo"S$value$data\n"; // to ARDUINO
 	 exit;
   }






?>
