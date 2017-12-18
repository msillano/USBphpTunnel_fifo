/*
  irTunnel_fifo.ino - Application of USBphpTunnel to remotesDB
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
    irTunnel_fifo
    Receive/transmit IR streams from/to Arduino using USBphpTunnel + upt_fifo    
    
    Uses an extensible generic protocol: SET or GET + COMMAND_ID + [data] = (S|G)(1|2|3)[<ascii data>]
    
    COMMANDS IMPLEMENTED  ('S'= SET, 'G'= GET):
    - IRSTREAM       '1'     TX/RX IR raw streams
    - FRAMETIMEOUT   '2'     SET/GET the FrameTimeout parameter used in reception by IRlib2 (to get 1 or more frames)
    - LOOPDELAY      '3'     SET/GET the timing between Arduino polling requests
    
      synchronous implementation because nothing else to do
    
    note:
       found some problems in TX timing: the current implementation, which uses a PString, works pretty much anyway.
  =========================================================================      
    Can be tested standalone, using Arduino 'serial monitor':
    Example: 
   ---------------    
   /upt_fifow.php       // the polling request: no response, repeat
   /upt_fifow.php
  > G2                  // type ph-request: GET FRAMETIMEOUT
   /upt_fifoget.php?id=2&data=7800   // result: default 7800
   /upt_fifow.php
  > S23000              // type ph-request: SET FRAMETIMEOUT 3000
   /upt_fifoset.php?id=2&data=3000   // resul: ok: set to 3000 
   /upt_fifow.php
   /upt_fifow.php
  > G3                  // type ph-request: GET LOOPDELAY
   /upt_fifoget.php?id=3&data=350   // result: default 350
   /upt_fifow.php
  > G2                  // type ph-request: GET FRAMETIMEOUT
   /upt_fifoget.php?id=2&data=3000   // result:  3000
   /upt_fifow.php
   /upt_fifow.php
   ..... but also like this: /upt_fifoget.php?id=1&data=(8970|-4510|506|-622|530|-598|506|-622|530|-602|526|-602|502|-626|502|-1726|506|-622|530|-1702|526|-1706|502|-1726|506|-1726|502|-1730|526|-1702|530|-598|506|-1726|502|-626|530|-1702|530|-598|502|-626|506|-622|502|-626|506|-622|530|-598|530|-1702|506|-622|506|-1726|502|-1726|530|-1702|530|-1702|506|-1726|502|-1726|558|-7000)
   ------------------
   note: as you can see, this sketch uses 3 php WEB pages:
                      upt_fifow.php: to pop from fifo a waiting request, and to send it back to Arduino  
                      upt_fifoset.php: to process the SET. Result: the set-value or error message 
                      upt_fifoget.php: to process the GET. Result: 'done' or actual-value or error message 
                                all error messages starts with '**'
   ========================================================================                     
    see https://github.com/msillano/remotesDB
    see https://github.com/msillano/remotesDBdiscovery
    see https://github.com/msillano/USBphpTunnel
    
Lo sketch usa 6590 byte (20%) dello spazio disponibile per i programmi. Il massimo è 32256 byte.
Le variabili globali usano 1516 byte (74%) di memoria dinamica, lasciando altri 532 byte liberi per le variabili locali. Il massimo è 2048 byte.
*/


// use IRLibRecvPCI
#include <PString.h>
#include <IRLibRecvPCI.h>     //RX interrupt driven
#include <IRLibSendBase.h>    //We need the base code
#include <IRLib_HashRaw.h>    //Only uses raw sender
//
IRrecvPCI myReceiver(2);      //pin number for the receiver, the Sender uses 3 by default (because PWM)
IRsendRaw mySender;
// =========== 'MAGIC'  STATUS NUMBERS
#define STATUS_START      1
#define STATUS_WAIT_DATA  2
#define STATUS_DONE       3
#define STATUS_END      100

// COMMAND FIRST CHAR
#define GET  'G'
#define SET  'S'

// COMMAND ID
#define  IRSTREAM      '1'
#define  FRAMETIMEOUT  '2'
#define  DELAYLOOP     '3'

#define RXTIMEOUT     10000
#define TXTIMEOUT     2000
//
int loopdelay = 350;     //COMMAND LOOPDELAY 
int delay2  =5;          // delay before send 
unsigned long start;     // for timeout
//
int astatus = 0;
// global values:
long localFrameTimeout = 7800;     //  = default see DEFAULT_FRAME_TIMEOUT in IRLib2/IRLibRecBase.h line 29
// data buffer: [the max length of IR streams]
uint16_t txrawData[RECV_BUF_LENGTH];   //RECV_BUF_LENGTH 254 in IRLib2/IRLibGlobals.h, line 27
// You MUST change it to 254. Arduino avalaible space limits the max size to about 300
//
uint16_t txindex = 0;
//
int txfrequence ;
int txdeltatime ;
uint16_t txdatalen ;
//
char sbuffer[50];
PString mystring(sbuffer, sizeof(sbuffer));

// STATUS URLS
// NOTE in Android, in file USBphpTunnel/config.ini you must update the
// Url first fragment, e.g.: phpPath=http\://localhost\:8080/upt_fifo
// to get full urls like: http://localhost:8080/upt_fifo/upt_fifow.php...
//

#define  UPT_WAIT   "/upt_fifow.php\n"
// messages:
char upt_get[]  =  "/upt_fifoget.php?id=";
char upt_set[]  =  "/upt_fifoset.php?id=";
char err1[] =  "**+VALUE+OUT+OF+BOUNDS";   // url encoded
char err2[] =  "**+ERROR+RX+timeout";
char err3[] =  "**+ERROR+TX+too+much+data";
char okmsg[] = "data sended";


void setup() {
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);
 // myReceiver.setFrameTimeout(localFrameTimeout);       // COMMAND FRAMETIMEOUT
 // myReceiver.enableAutoResume(rawData);
  Serial.begin(115200);              // same in USBphpTunnel/config.ini
 // Serial.begin(9600);

  while (!Serial) {
    ; // wait for serial port to connect. Needed for native USB port only
   }
}

int tcount = 1000;     // for timing polling

void loop() {
  //  RX LOOP 
  delay(10);  // loop delay 
  while (Serial.available() > 0) {
    int x =  Serial.read();
      switch (x) {
      case '\n': break;    
      case  GET: {
          Serial.print("*G");                      // echo
          doGetCommand();  // GET broker
          tcount = 0;   // restart timing
          break;
        }
      case  SET: {
           Serial.print("*S");                      // echo
         doSetCommand();  // SET broker
          tcount = 0;   // restart timing
          break;
         }
      }
    }
  // =========  TX  W LOOP
  tcount++;
  if (tcount > loopdelay) {    // polling period
      tcount = 0;
      digitalWrite(LED_BUILTIN, HIGH);  // led blink fast for Arduino polling
      delay(8);
      Serial.print(UPT_WAIT);
      digitalWrite(LED_BUILTIN, LOW);
   }
}

// ================================== brokers
//  GET broker: synchronous, read command, elabote, send answer
void   doGetCommand() {
  char x = Serial.read();
  if (x == IRSTREAM) {     // IR read
        Serial.print("1");                      // echo
       digitalWrite(LED_BUILTIN, HIGH);
        astatus = STATUS_START;
        while (astatus != STATUS_END)
           astatus =  doGETIR( astatus );    // subautomata to RX IR
        digitalWrite(LED_BUILTIN, LOW);
        return;
        }
  if (x == FRAMETIMEOUT) {     
         Serial.print("2");                      // echo
      sendUrl(upt_get, FRAMETIMEOUT, localFrameTimeout);
        return;
        }
  if (x == DELAYLOOP) {  
        Serial.print("3");                      // echo
       sendUrl(upt_get, DELAYLOOP, loopdelay);
        return;
        }
}

// SET broker: synchronous, read command, elabote, send answer
void   doSetCommand() {
  uint16_t itemp = 0;
  char x = Serial.read();

  switch (x) {
     case '\n': return;
     case (IRSTREAM):                 // IR send
        Serial.print("1");                      // echo
     digitalWrite(LED_BUILTIN, HIGH);
      astatus = STATUS_START;
      while (astatus != STATUS_END)
           astatus =  doSETIR( astatus );   //  subautomata  to TX IR
      digitalWrite(LED_BUILTIN, LOW);
      return;
    case (FRAMETIMEOUT):
         Serial.print("2");                      // echo
         localFrameTimeout = Serial.parseInt();
         if (localFrameTimeout < 4000)            // adjust values out of bound
                   localFrameTimeout = 4000;      // never error
         if (localFrameTimeout > 60000)     
                   localFrameTimeout = 60000;            
          myReceiver.setFrameTimeout((uint16_t) localFrameTimeout);       // COMMAND FRAMETIMEOUT
          sendUrl(upt_set, FRAMETIMEOUT, localFrameTimeout);
          return;
    case (DELAYLOOP):
       Serial.print("3");                        // echo
       itemp = Serial.parseInt();                // COMMAND LOOPDELAY
       if ((itemp >60) && (itemp <= 1000 )){
         loopdelay  = itemp;
         sendUrl(upt_set, DELAYLOOP, loopdelay);  
       } else
         {
          sendMessg(upt_set, DELAYLOOP,err1);    // error if out of bounds
          }
    }
}

//================================================== single process subautomata

int doGETIR(int status) {
    switch (status) {
     case (STATUS_START):
       start = millis();
       myReceiver.enableIRIn(); // Start the receiver
       Serial.print("\n");                      // echo
       return STATUS_WAIT_DATA;
    case (STATUS_WAIT_DATA):
      if (myReceiver.getResults()) {
        while(Serial.availableForWrite() < 60) delay(1);
        Serial.print( upt_get);
        Serial.print(IRSTREAM);
        Serial.print(F( "&data=(" ));
        for (bufIndex_t i = 1; i < recvGlobal.recvLength; i++) {
           while(Serial.availableForWrite() <10) delay(1);
           if ( i % 2 == 1) {
             Serial.print(recvGlobal.recvBuffer[i], DEC);
             Serial.print(F("%7C-"));
          }
          else {
             Serial.print(recvGlobal.recvBuffer[i], DEC);
             Serial.print(F("%7C"));
          }
        }
        Serial.print(7000, DEC); //Add arbitrary trailing space
        Serial.print(F(")"));
        Serial.print("\n");
        Serial.flush();
       
  //    myReceiver.enableIRIn();      //Restart receiver
        myReceiver.disableIRIn();
        return STATUS_END;
      }
      else  {
        if (millis() - start > RXTIMEOUT) {
         sendMessg(upt_get, IRSTREAM, err2);
         return STATUS_END;
        }
      }
  }
  return STATUS_WAIT_DATA;
}

int doSETIR(int status) {
  switch (status) {
  
     case (STATUS_START):
      start = millis();
      txfrequence = Serial.parseInt();
      txdeltatime = Serial.parseInt();
      txdatalen   = Serial.parseInt();
      txindex = 0;
      if (txdatalen >  RECV_BUF_LENGTH){
            clearin();
            sendMessg(upt_set, IRSTREAM, err3);
            return STATUS_END;      
      }
      return STATUS_WAIT_DATA;
            
    case (STATUS_WAIT_DATA):
      //    Serial.print("*RX ");                              //  starts with '*': echo for USBphpTunnel, debug
      int data = 0;
      while  (Serial.available() > 4) {
        data = Serial.parseInt();
        if (data < 0) 
           txrawData[txindex++] = -data * txdeltatime;
        if (data > 0)
           txrawData[txindex++] = data * txdeltatime;
     //         Serial.print(data);
     //         Serial.print(":");
           if (txindex >= txdatalen) {                    // done
     //         delay(delay2);
     //         while(Serial.availableForWrite() < 60) delay(1);
     //         Serial.print("ok\n");                        
     //         Serial.flush();                            // echo ends
             mySender.send(txrawData, txdatalen, txfrequence);  //Pass the buffer,length, optionally frequency
             clearin();
             delay(100);
             sendMessg(upt_set, IRSTREAM,okmsg);
             return STATUS_END;
             }  
        } // ends while available
   //      Serial.print('@');
   //      Serial.println(txindex);                       // echo debug
   // in case of less data...
      if (millis() - start > TXTIMEOUT) {
   //         Serial.print("bad\n");                        
   //         Serial.flush();                             // echo ends
        delay(delay2);
        while(Serial.availableForWrite() < 60) delay(1);
        sendMessg(upt_set, IRSTREAM, err2);
        return STATUS_END;
       }
    }
  delay(3);
  return STATUS_WAIT_DATA;
 }

 // =============== unified send using a PString
 
void sendUrl(char* to, char val, long data){
  Serial.print("--\n");                      // echo

 delay(delay2);
 while(Serial.availableForWrite() < 60) delay(1);
 mystring.begin();
 mystring.print(to);
 mystring.print(val);
 mystring.print("&data=");
 mystring.print(data);
 mystring.print('\n');
 Serial.print("   ");
 Serial.write(sbuffer);
 Serial.flush();
}

void sendMessg(char* to, char val, char* messg){
 Serial.print("++\n");                      // echo
 delay(delay2);
 while(Serial.availableForWrite() < 60) delay(1);
 mystring.begin();
 mystring.print(to);
 mystring.print(val);
 mystring.print("&data=");
 mystring.print(messg);
 mystring.print('\n');
 Serial.print("   ");
 Serial.write(mystring);
 Serial.flush();
}

void clearin(){
  while (Serial.available() > 0)  Serial.read();
}


