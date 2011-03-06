<?php

function php_scgi_client_conf__get_conf() {
    $conf = array(
        // path (URL) to socket file:
        'SOCKET_FILE' => 'unix://'.realpath(dirname(__FILE__).'/var/scgi.sock'),
        
        // automatic start SCGI-daemon when first HTTP-request:
        //'SCGI_DAEMON_AUTO_START' => FALSE,
        
        // command line for SCGI-daemon start:
        //'SCGI_DAEMON_START_CMD' => dirname(__FILE__).'/scgi-daemon-start',
    );
    
    return $conf;
}
