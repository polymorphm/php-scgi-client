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
    'SHOW_RESPONSE_TIME' => TRUE,
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
    
    if (array_key_exists('REDIRECT_URL', $_SERVER)) {
        $environ['PATH_INFO'] = $_SERVER['REDIRECT_URL'];
    }
    if (array_key_exists('REDIRECT_QUERY_STRING', $_SERVER)) {
        $environ['QUERY_STRING'] = $_SERVER['REDIRECT_QUERY_STRING'];
    }
    
    $conf = php_scgi_client__get_conf();
    $get_cgi_environ_hook = $conf['GET_CGI_ENVIRON_HOOK'];
    
    if($get_cgi_environ_hook) {
        require_once $get_cgi_environ_hook;
        
        php_scgi_client__get_cgi_environ_hook($environ);
    }
    
    return $environ;
}

function php_scgi_client__stripslashes_if_gpc($str) {
    if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        $str = stripslashes($str);
    }
    
    return $str;
}

function php_scgi_client__format_multipart_post_data(&$environ) {
    $boundary = '--------------------'.
            rand(0,99999999).'-'.rand(0,99999999).
            '-'.rand(0,99999999).'-'.rand(0,99999999);
    
    $result_list = array();
    
    foreach($_POST as $name => $raw_data) {
        $data = php_scgi_client__stripslashes_if_gpc(strval($raw_data));
        
        $result_list []= sprintf('--%s', $boundary);
        $result_list []= sprintf('Content-Disposition: form-data;name="%s"',
                str_replace("\n", "\\n", str_replace("\"", "\\\"", str_replace("\\", "\\\\", $name))));
        $result_list []= '';
        $result_list []= $data;
    }
    
    foreach($_FILES as $name => $file_info) {
        if(array_key_exists('tmp_name', $file_info) && $file_info['tmp_name']) {
            $data_path = strval($file_info['tmp_name']);
            $data = @file_get_contents($data_path);
        } else {
            $data = '';
        }
        
        $file_name = array_key_exists('name', $file_info)?
                strval($file_info['name']):$name;
        $file_type = array_key_exists('type', $file_info) && $file_info['type']?
                strval($file_info['type']):'application/octet-stream';
        
        $result_list []= sprintf('--%s', $boundary);
        $result_list []= sprintf('Content-Disposition: form-data;name="%s";filename="%s"',
                str_replace("\n", "\\n", str_replace("\"", "\\\"", str_replace("\\", "\\\\", $name))),
                str_replace("\n", "\\n", str_replace("\"", "\\\"", str_replace("\\", "\\\\", $file_name))));
        $result_list []= 'Content-Type: '.$file_type;
        $result_list []= '';
        $result_list []= $data;
    }
    
    $result_list []= sprintf('--%s--', $boundary);
    
    $result = join("\r\n", $result_list);
    $environ['CONTENT_TYPE'] = 'multipart/form-data;boundary='.$boundary;
    
    return $result;
}

function php_scgi_client__get_post_data(&$environ) {
    global $php_scgi_client__post_data_cache;
    
    if($php_scgi_client__post_data_cache === NULL) {
        $php_scgi_client__post_data_cache = file_get_contents('php://input');
        
        if(array_key_exists('CONTENT_TYPE', $environ) &&
                substr($environ['CONTENT_TYPE'], 0, strlen('multipart/form-data;'))
                == 'multipart/form-data;' &&
                !strlen($php_scgi_client__post_data_cache)) {
            $php_scgi_client__post_data_cache =
                    php_scgi_client__format_multipart_post_data($environ);
        }
    }
    
    return $php_scgi_client__post_data_cache;
}

function php_scgi_client__get_conf() {
    global $PHP_SCGI_CLIENT__DEFAULT_CONF;
    global $PHP_SCGI_CLIENT__CONF_PATH;
    
    $conf = $PHP_SCGI_CLIENT__DEFAULT_CONF;
    if($PHP_SCGI_CLIENT__CONF_PATH && file_exists($PHP_SCGI_CLIENT__CONF_PATH)) {
        require_once $PHP_SCGI_CLIENT__CONF_PATH;
        
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
    $post_data = php_scgi_client__get_post_data($environ);
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

function php_scgi_client__format_status_header($status_value) {
    // Apache HTTP Server -- not understands header "Status: ..."
    // but it understands header "HTTP/X.Y ..."
    
    if(!array_key_exists('SERVER_PROTOCOL', $_SERVER) || !$_SERVER['SERVER_PROTOCOL']) {
        throw new php_scgi_client__error('HTTP-server not defined parameter \'SERVER_PROTOCOL\'');
    }
    
    $header = $_SERVER['SERVER_PROTOCOL'].' '.$status_value;
    
    return $header;
}

function php_scgi_client__fix_status_header($header) {
    if(substr($header, 0, strlen('Status: ')) == 'Status: ') {
        $status_value = substr($header, strlen('Status: '));
        $header = php_scgi_client__format_status_header($status_value);
    }
    
    return $header;
}

function php_scgi_client__additional_headers($kwargs=NULL) {
    $conf = php_scgi_client__get_conf();
    $http_x_powered_by = $conf['HTTP_X_POWERED_BY'];
    
    if($http_x_powered_by) {
        header('X-Powered-By: '.$http_x_powered_by, FALSE);
    }
    
    if($kwargs && array_key_exists('response_time', $kwargs)) {
        $response_time = $kwargs['response_time'];
        $show_response_time = $conf['SHOW_RESPONSE_TIME'];
        
        if($show_response_time) {
            header('X-Response-Time: '.$response_time, FALSE);
        }
    }
}

function php_scgi_client__get_microtime() {
    $raw_microtime = microtime();
    
    list($usec, $sec) = explode(' ', $raw_microtime);
    $microtime = array(
        'sec' => intval($sec),
        'usec' => floatval($usec),
    );
    
    return $microtime;
}

function php_scgi_client__get_microtime_subtraction($begin_mt) {
    $end_mt = php_scgi_client__get_microtime();
    
    $accuracy = 0x0100;
    
    $begin_t = $begin_mt['sec'] % $accuracy + $begin_mt['usec'];
    $end_t = $end_mt['sec'] % $accuracy + $end_mt['usec'];
    
    $sub_t = $end_t - $begin_t;
    
    if($sub_t < 0) {
        $sub_t += $accuracy;
    }
    
    return $sub_t;
}

function php_scgi_client__main() {
    try {
        $fd = php_scgi_client__fsockopen();
        
        $begin_response_mt = php_scgi_client__get_microtime();
        
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
                    $header = php_scgi_client__fix_status_header($header);
                }
                
                header($header, FALSE);
            } else {
                break;
            }
        }
        
        $response_time = php_scgi_client__get_microtime_subtraction($begin_response_mt);
        
        php_scgi_client__additional_headers(array(
            'response_time' => $response_time,
        ));
        
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
        @header(php_scgi_client__format_status_header('500 Internal Server Error'));
        @header('Content-Type: text/plain;charset=utf-8');
        
        echo 'Error: '.$e->getMessage();
    }
    
    if(isset($fd)) {
        @fclose($fd);
    }
}
