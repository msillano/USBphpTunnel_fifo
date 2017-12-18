<?php
/*
  upt_fifo.php - This library is part of USBphpTunnel_fifo.
  
  USBphpTunnel_fifo is free software; you can redistribute it and/or
  modify it under the terms of the GNU Lesser General Public
  License as published by the Free Software Foundation; either
  version 2.1 of the License, or (at your option) any later version.

  USBphpTunnel_fifo is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  Lesser General Public License for more details.

  You should have received a copy of the GNU Lesser General Public
  License along with this library; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  --------
  Copyright (c) 2017 Marco Sillano.  All right reserved.
 */
 /*
 Small library for USBphpTunnel fifo.
 This is an extension to USBphpTunnel with the goals:
    1) make php master (in USBphpTunnel Arduino is master)
    2) have asynchronous communications.
    3) allow concurrence
 This extension is general pourpose, but the demo uses a sketch developped for remotesDB.
 
 HOW IT WORKS
 step1: The php 'push' a request in the fifo.
 step2: The Arduino tests in polling the fifo and gets the request.
 step3: Arduino processes the request and update the fifo with the result.
 step4: The php can now to get the response.
 
 IMPLEMENTATION
 Protocol:
   upt_fifo uses an extensible generic protocol: SET or GET + COMMAND_ID + [data] = (S|G)(1|2|3)[<ascii data>]
   (e.g.: G2, S32000 etc.). Error messages from Arduino in data, starting by '*'. '\n' is terminator.
 Fifo:
   The fifo is implemented as a DB table: (see fifo_table.sql) having the fields:
     `id`:  auto_increment request ID (for concurrence)
     `time` creation timestamp 
     `endtime` end timestamp 
     `type` enum('SET','GET') NOT NULL,
     `status` enum('WAIT','PROCESS','READY','DONE') the message evolution
     `value` int(3) the command selector (in demo implementation is limited to 1..3)
     `data` varchar(4000) data sended by php (SET) or data sended by Arduino (GET)
   The records in fifo are not deleted.
 Library:
   In this file all primitives for fifo management.
   The following pages makes an Automa triggered by Arduino:
     upt_fifow.php : for arduino polling, returns (if any) the ASCII request
     upt_fifoset.php: close a SET, update in error case 
     upt_fifoset.php: update a GET with data|error_message
   
   The main php program uses: 
      - pushGETrequest()  or   pushSETrequest()
      - statusRequest()
      - popGETrequest()
      
 see https://github.com/msillano/USBphpTunnel_fifo
 Used by upt_fifoget.php,  upt_fifoget.php,  upt_fifow.php.
 Main page for demo-test:  upt_test_arduino.php,  
 */
$f=dirname(dirname(__FILE__));
require_once("$f/remoteDB/irp_commonSQL.php");
    
  
// ====================================== FOR Arduino automa functions
// returns 'SET'/'GET'/NULL
function peekNextRequest(){
  return sqlValue("SELECT `type` FROM fifo WHERE status='WAIT'  ORDER BY id ASC LIMIT 1");  // to be processed
}
// returns record array
function peekLastRequest(){
  return sqlRecord("SELECT * FROM fifo WHERE status='PROCESS' ORDER BY id ASC LIMIT 1");  // process not ends
  }

// ---------------------- GET
// return value, change WAIT to PROCESSED
function peekGETrequest(){
list($id, $value) = sqlRecord("SELECT id, `value` FROM fifo WHERE  `type` = 'GET'   AND status='WAIT'  ORDER BY id DESC LIMIT 1");
if ( $id == NULL) return false;
//echo "* peek id = $id\n";
sql("UPDATE fifo SET status='PROCESS' WHERE id = $id;");
return $value;
}

// set data,  status READY
FUNCTION closeGETrequest( $value, $data){
$id = SQLVALUE("SELECT id FROM fifo WHERE  `type` = 'GET' AND `value` = $value  AND status = 'PROCESS'  ORDER BY ID DESC LIMIT 1");
// echo "* close id $id \n";
if ( $id == NULL) return false; 
SQL("UPDATE fifo SET status='READY', `data` = '$data' WHERE id = $id ");
RETURN $id;
}
// ------------------------ SET
// note: no READY status for SET
// return id,  value, data. set status PROCESSED
function peekSETrequest(){  
 $tmp = array();
 $tmp = sqlRecord("SELECT id, `value`, `data` FROM fifo WHERE  `type` = 'SET'  AND status='WAIT'  ORDER BY id DESC LIMIT 1");
 if ( $tmp[0] == NULL) return false; 
 //echo "* peek id = ".$tmp['id']."\n";
 sql("UPDATE fifo SET status='PROCESS' WHERE id = ".$tmp['id']);
 return $tmp;
}

// get the oldest request, set it DONE/BAD
// data = NULL: DONE, data not changed from request
// data = xxxx: DONE, but data changed by Arduino, fifo updated
// data = *ERROR_message : BAD
function closeSETrequest($value, $data = NULL){
$id = sqlValue("SELECT id FROM fifo WHERE  `type` = 'SET'  AND `value` = $value  AND status='PROCESS'  ORDER BY id DESC LIMIT 1");
echo "* close id $id \n";
if ( $id == NULL) return false; 
if (($data != NULL) && ($data[0] == '*')) 
    sql("UPDATE fifo SET status='BAD' ,  `data` = '$data',  endtime = NOW() WHERE id = $id");
else 
    sql("UPDATE fifo SET status='DONE' ,".(($data != NULL)?"`data` = '$data',":"")." endtime = NOW() WHERE id = $id");
return $id;
}


// ===================== FOR MAIN PHP PROCESS
// return id
function pushGETrequest($value){
	sql("INSERT INTO fifo SET `type` = 'GET', `value` = $value, status = 'WAIT'");
	return sqlValue("SELECT LAST_INSERT_ID();");
}

// return data, error or NULL
// param id, or get the last request, set it DONE/BAD
// sets endtime, status (DONE|BAD)
function popGETrequest($id = NULL){
if ($id == NULL){
     $id = sqlValue("SELECT id FROM fifo WHERE  `type` = 'GET' AND status='READY'  ORDER BY id DESC LIMIT 1");
}
if ( $id == NULL) return NULL;
$fifo =sqlRecord("SELECT * FROM fifo WHERE id = $id");
 
if ($fifo['status'] == 'READY'){
  $data = $fifo['data'];
  if (($data == NULL) || ($data[0] == '*'))
     sql("UPDATE fifo SET status='BAD', endtime = NOW() WHERE id = $id");
else 
     sql("UPDATE fifo SET status='DONE' , endtime = NOW() WHERE id = $id");
  return $data;
     }
 return NULL;     
}

// return id
function pushSETrequest($value, $data){
	  sql("INSERT INTO fifo SET `type` = 'SET', `value` = $value, status='WAIT', `data` ='$data'");
  	return sqlValue("SELECT LAST_INSERT_ID();");
}

// return data, error or NULL
// param id, or get the last request
function popSETrequest($id = NULL){
if ($id == NULL){
     $id = sqlValue("SELECT id FROM fifo WHERE  `type` = 'SET' AND (status='DONE' OR status='BAD')  ORDER BY id DESC LIMIT 1");
}
if ( $id == NULL) return NULL;
return sqlValue("SELECT data FROM fifo WHERE id = $id");
}


// return status 
function statusRequest($id){
    return sqlValue("SELECT status FROM fifo WHERE id = $id");
}
// ==================================================== more  function, statistics

// test: how many GET request?  total (NULL) or per value)
function countGETrequest( $value =  NULL){
$query = "SELECT count(*) FROM fifo WHERE  `type` = 'GET'";
if ($value != NULL)
    $query .= " AND `value` = $value ";
return sqlValue($query);
}

// return SET count
function countSETrequest( $value =  NULL){
$query = "SELECT count(*) FROM fifo WHERE  type = 'SET'";
if ($value != NULL)
    $query .= " AND `value` = $value ";
return sqlValue($query);
}

function getRequestDuration($id){  
return  sqlValue("SELECT IF(endtime IS NOT NULL,TIMESTAMPDIFF(SECOND,time,endtime),0) FROM fifo WHERE id =$id;");
}

?>
