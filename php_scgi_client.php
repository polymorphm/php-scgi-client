<?php
// -*- mode: php; coding: utf-8 -*-
//
// Copyright 2011 Andrej A Antonov <polymorphm@gmail.com>
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

$PHP_SCGI_CLIENT__DEFAULT_CONF = array(
    'SOCKET_FILE' => NULL,
    'SCGI_DAEMON_AUTO_START' => FALSE,
    'SCGI_DAEMON_START_CMD' => escapeshellarg(dirname(__FILE__).'/scgi-daemon-start'),
    'SCGI_DAEMON_START_CMD_SLEEP' => 3.0,
    'GET_CGI_ENVIRON_HOOK' => NULL,
    'HTTP_X_POWERED_BY' => 'php-scgi-client (2011-03-04-php-scgi-client at github.com)',
);

$PHP_SCGI_CLIENT__CGI_ENVIRON_BLACK_LIST = array(
    // for protecting from dublication (when headers formating process):
    'CONTENT_LENGTH',
    'SCGI',
    
    // for protecting from PHP specifics:
    'SCRIPT_FILENAME',
    'SCRIPT_NAME',
    'PATH_TRANSLATED',
    'PHP_SELF',
);

$php_scgi_client__post_data_cache = NULL;

class php_scgi_client__error
        extends Exception {}

class php_scgi_client__connection_error
        extends php_scgi_client__error {}

function php_scgi_client__get_cgi_environ() {
    global $PHP_SCGI_CLIENT__CGI_ENVIRON_BLACK_LIST;
    
    $environ = array(
        'SCRIPT_NAME' => '',
        'PATH_INFO' => '',
        'QUERY_STRING' => '',
    );
    
    foreach($_SERVER as $k => $v) {
        if(!in_array($k, $PHP_SCGI_CLIENT__CGI_ENVIRON_BLACK_LIST)) {
            $environ[$k] = strval($v);
        }
    }
    
    $conf = php_scgi_client__get_conf();
    $get_cgi_environ_hook = $conf['GET_CGI_ENVIRON_HOOK'];
    
    if($get_cgi_environ_hook) {
        require_once $get_cgi_environ_hook;
        
        php_scgi_client__get_cgi_environ_hook($environ);
    }
    
    return $environ;
}

function php_scgi_client__get_post_data() {
    global $php_scgi_client__post_data_cache;
    
    if($php_scgi_client__post_data_cache === NULL) {
        $php_scgi_client__post_data_cache = file_get_contents('php://input');
    }
    
    return $php_scgi_client__post_data_cache;
}

function php_scgi_client__get_conf() {
    global $PHP_SCGI_CLIENT__DEFAULT_CONF;
    
    $conf = $PHP_SCGI_CLIENT__DEFAULT_CONF;
    $conf_file = dirname(__FILE__).'/php_scgi_client_conf.php';
    
    if(file_exists($conf_file)) {
        require_once $conf_file;
        
        $conf = array_merge($conf,
                php_scgi_client_conf__get_conf());
    }
    
    return $conf;
}

function php_scgi_client__fsockopen_or_error() {
    $conf = php_scgi_client__get_conf();
    $socket_file = $conf['SOCKET_FILE'];
    
    if(!$socket_file) {
        throw new php_scgi_client__error('Parameter \'SOCKET_FILE\' is not configured');
    }
    
    $fd = @fsockopen($socket_file);
    
    if($fd) {
        return $fd;
    } else {
        throw new php_scgi_client__connection_error($socket_file.': Can\'t connect to socket file');
    }
}

function php_scgi_client__fsockopen() {
    try {
        $fd = php_scgi_client__fsockopen_or_error();
    } catch(php_scgi_client__connection_error $e) {
        $conf = php_scgi_client__get_conf();
        
        if($conf['SCGI_DAEMON_AUTO_START']) {
            $cmd = $conf['SCGI_DAEMON_START_CMD'];
            
            if(!$cmd) {
                throw new php_scgi_client__error('Parameter \'SCGI_DAEMON_START_CMD\' is not configured');
            }
            
            // start SCGI-daemon:
            system($cmd);
            sleep($conf['SCGI_DAEMON_START_CMD_SLEEP']);
            
            // second trying to connect:
            $fd = php_scgi_client__fsockopen_or_error();
        } else {
            throw $e;
        }
    }
    
    return $fd;
}

function php_scgi_client__format_output() {
    $environ = php_scgi_client__get_cgi_environ();
    $post_data = php_scgi_client__get_post_data();
    $content_length = strlen($post_data);
    
    $headers =
            sprintf("%s\x00%s\x00", 'CONTENT_LENGTH', $content_length).
            sprintf("%s\x00%s\x00", 'SCGI', 1);
    
    foreach($environ as $k => $v) {
        $headers .= sprintf("%s\x00%s\x00", $k, $v);
    }
    
    $output = strlen($headers).':'.$headers.','.$post_data;
    
    return $output;
}

function php_scgi_client__format_status_header($header) {
    // Apache HTTP Server -- not understands header "Status: ..."
    // but it understands header "HTTP/X.Y ..."
    
    if(!array_key_exists('SERVER_PROTOCOL', $_SERVER)) {
        throw new php_scgi_client__error('HTTP-server not defined parameter \'SERVER_PROTOCOL\'');
    }
    
    if(substr($header, 0, strlen('Status: '))) {
        $header = $_SERVER['SERVER_PROTOCOL'].' '.substr($header, strlen('Status: '));
    }
    
    return $header;
}

function php_scgi_client__additional_headers() {
    $conf = php_scgi_client__get_conf();
    $http_x_powered_by = $conf['HTTP_X_POWERED_BY'];
    
    if($http_x_powered_by) {
        header('X-Powered-By: '.$http_x_powered_by, FALSE);
    }
}

function php_scgi_client__main() {
    try {
        $fd = php_scgi_client__fsockopen();
        
        $output = php_scgi_client__format_output();
        
        for($all_written = 0; $all_written < strlen($output); $all_written += $written) {
            $written = @fwrite($fd, substr($output, $all_written));
            if($written === FALSE || !$written) {
                break;
            }
        }
        
        @fflush($fd);
        
        $is_first_header = TRUE;
        for(;;) {
            if(!feof($fd)) {
                $raw_header = @fgets($fd);
            } else {
                break;
            }
            
            if($raw_header !== FALSE && strlen($raw_header) !== 0) {
                $header = trim($raw_header);
            } else {
                break;
            }
            
            if($header) {
                if($is_first_header) {
                    $is_first_header = FALSE;
                    $header = php_scgi_client__format_status_header($header);
                }
                
                header($header, FALSE);
            } else {
                break;
            }
        }
        
        php_scgi_client__additional_headers();
        
        for(;;) {
            if(!feof($fd)) {
                $data = @fread($fd, 8192);
            } else {
                break;
            }
            
            if($data !== FALSE && strlen($data) !== 0) {
                echo $data;
            } else {
                break;
            }
        }
    } catch(php_scgi_client__error $e) {
        @header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');
        @header('Content-Type: text/plain;charset=utf-8');
        
        echo 'Error: '.$e->getMessage();
    }
    
    if(isset($fd)) {
        @fclose($fd);
    }
}
