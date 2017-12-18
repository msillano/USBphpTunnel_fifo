<?php
/*
  Example for USBphpTunnel_fifo (https://github.com/msillano/USBphpTunnel_fifo)
  Copyright (c) 2017 Marco Sillano.  All right reserved.

  This library is free software; you can redistribute it and/or
  modify it under the terms of the GNU Lesser General Public
  License as published by the Free Software Foundation; either
  version 2.1 of the License, or (at your option) any later version.

  This library is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  Lesser General Public License for more details.

  You should have received a copy of the GNU Lesser General Public
  License along with this library; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/*
This demo page tests the USBphpTunnel_fifo library.

INSTALLATION:
pre: You MUST have an Android computer (TVbox) with Web server (e.g. Palapa), php, phpmyAdmin
     and USBphpTunnel.apk installed and working. 
     An Arduino board and Arduino IDE. The IR hardware is not required to run this test.
     (see https://github.com/msillano/USBphpTunnel)
1 -  Copy www/*.* to WEB server directory  (e.g. /mnt/shell/emulated/0/pws/www/ )
2 -  Using phpmyAdmin you create a DB 'remotesdb' and you import the file sql to create the fifo table.
3 -  You compile and upload the sketch irTunnel_fifo.ino on the Arduino board
4 -  Find USBphpTunnel/config.ini:  (e.g. /mnt/shell/emulated/0/USBphpTunnel/config.ini)
      -  The serial speed in irTunnel_fifo.ino and in USBphpTunnel/config.ini must be equals.
      -  Update the phpPath (e.g. http\://localhost\:8080/upt_fifo) in USBphpTunnel/config.ini 

DEMO:
    Open this page (/upt_fifo/upt_test_arduino.php)  in a Browser.
*/
$f=dirname(__FILE__);
require_once("$f/../remoteDB/irp_commonSQL.php");
require_once ("$f/upt_fifo.php");

echo "<html><head></head><body>";
echo "<h2> Test Arduino via USBphpTunnel_fifo</h2>";

if (isset($_GET['id']))
    echo 'Actual ID: '.$_GET['id'].'<br>';

$id = NULL;
if (isset($_GET['mode'])){
	if ($_GET['mode'] == 'GET'){
	    $id =  pushGETrequest($_GET['val']);
	} else {
	    $id =  pushSETrequest($_GET['val'], $_GET['data'] );
	}
$record = sqlRecord ("SELECT * FROM fifo WHERE id = $id ");
 echo "<b>FIFO record is:</b><br>";
 echo '<pre>'; print_r($record); echo '</pre>';
}

if (isset($_GET['id'])){
  $id = $_GET['id'];
  $record = sqlRecord("SELECT * FROM fifo WHERE id = ".$_GET['id'] );
  echo "<b>FIFO record is:</b><br>";
  echo '<pre>'; print_r($record); echo '</pre>';

  if (($record['type']=='GET') && ($record['status']=='READY')){
	  $r = popGETrequest();
	  echo "<b> READY: popGETrequest() returns $r </b> <br><br>";    
	  }
  if (($record['type']=='SET') && (($record['status']=='DONE')||($record['status']=='BAD'))){
	  $r = popSETrequest();
	  echo "<b> popSETrequest() returns $r </b> <br><br>";    
	  }
    
}

if ($id != NULL){
	echo "<b>Actual status of request #".$id." is ".$record['status']."</b><br><br>";
	if ($record['status']!='DONE'){
		echo '<form action "upt_test_Arduino.php" mode="GET">';
		echo '<input type="hidden" name="id" value='.$id.'>';
		echo '<input type="submit" value="CONTINUE"> to see evolution of request #'.$id.'.</form>';
		}
}

echo '<hr><form action "upt_test_Arduino.php" mode="GET">';
echo '<input type="radio" name="mode" value="SET" >SET</input>
      <input type="radio" name="mode" value="GET" checked=true >GET</input><br><br>';
echo '<input type="radio" name="val" value="1">STREAM (value 1)</input>
      <input type="radio" name="val" value="2" checked=true >FRAME (value 2)</input>
      <input type="radio" name="val" value="3">LOOP (value 3)</input>  <br>
      <small> note: SET/GET STREAM only if IR hardware is in place.</small><br><br>';
echo 'Only for set - data:<input type="text" name="data" > <br><br><input type="submit" value="TEST"> </form>';

echo '</body></html>';
?>