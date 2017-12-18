<?php
$f=dirname(__FILE__);
require_once("$f/../remoteDB/irp_commonSQL.php");
require_once ("$f/upt_fifo.php");
     
// close a get (id => value)

if (isset($_GET['id']) && isset($_GET['data'])) {
   echo "* fifoGET.php called by arduino id_command:".$_GET['id']." data:". $_GET['data']."\n";
   closeGETrequest( $_GET['id'], $_GET['data']);
} else {
   echo "* fifoSET.php called by arduino, bad params\n";
}

     
     
     
     
     
     
     
?>