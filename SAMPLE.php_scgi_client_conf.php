<?php

function php_scgi_client_conf__get_conf () {
    $conf = array(
        // Path (URL) to socket file:
        'SOCKET_FILE' => 'unix://'.realpath(dirname(__FILE__).'/var/scgi.sock'),
        
        // Automatic start SCGI-daemon when first HTTP-request:
        //'SCGI_DAEMON_AUTO_START' => FALSE, // this is default value
        
        // Command line for SCGI-daemon start:
        //'SCGI_DAEMON_START_CMD' =>
        //        escapeshellarg(dirname(__FILE__).'/scgi-daemon-start'), // this is default value
        
        // Delay (in seconds) after SCGI-daemon start:
        //'SCGI_DAEMON_START_CMD_SLEEP' => 3.0, // this is default value
        
        // Using modified environ values:
        //'USE_CGI_ENVIRON_HOOK' => TRUE, // default value is FALSE
    );
    
    return $conf;
}

// function php_scgi_client_conf__cgi_environ_hook (&$environ) {
//      $environ['PATH_INFO'] = '/this/is/example/path';
//}
