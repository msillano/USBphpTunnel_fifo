# USBphpTunnel_fifo

 This is an extension to USBphpTunnel with the goals:
  - make php master (in USBphpTunnel Arduino is master)
  - have asynchronous communications.
  - allow concurrence
    
 This extension is general pourpose, but the demo uses an Arduino sketch developped for remotesDB
 
 ![MXQ and Arduino](./img/Arduino,jpg)
 
 
 
 
 
 
 
 
 
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
      
