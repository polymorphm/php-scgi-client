<?php

$PHP_SCGI_CLIENT_CONF__CONF = array(
    // path (URL) to socket file:
    'SOCKET_FILE' => 'unix://'.realpath(dirname(__FILE__).'/scgi.sock')
    
    // automatic start SCGI-daemon when first HTTP-request:
    //'SCGI_DAEMON_AUTO_START' => FALSE,
    
    // command line for SCGI-daemon start:
    //   (do not forget to write char '&' at the end of command, if necessary)
    //'SCGI_DAEMON_START_CMD' => ...,
    
    // number of microseconds delay, after SCGI-daemon start,
    // before HTTP-request processing:
    //'SCGI_DAEMON_START_SLEEP' => 3000,
);
