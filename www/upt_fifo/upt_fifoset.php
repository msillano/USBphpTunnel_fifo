<?php
$f=dirname(__FILE__);
require_once("$f/../remoteDB/irp_commonSQL.php");
require_once ("$f/upt_fifo.php");
  
// end of set  (id => value)
// update status to DONE or BAD + error
if (isset($_GET['id']) ) {
  echo "* fifoSET.php called by arduino id_command:".$_GET['id']."\n";
   if (isset($_GET['data'])) 
      closeSETrequest( $_GET['id'], $_GET['data']);
   else 
      closeSETrequest( $_GET['id']);

  } else {
  echo "* fifoSET.php called by arduino. BAD params";
  }
     
     
     
     
     
     
     
?>