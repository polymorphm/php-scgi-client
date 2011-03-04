<?php

// This file is part of "PHP SCGI Client"
// (see <https://github.com/2011-03-04-php-scgi-client/php-scgi-client>).
//
// "PHP SCGI Client" is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// "PHP SCGI Client" is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with "PHP SCGI Client".  If not, see <http://www.gnu.org/licenses/>.

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
    
    return $environ;
}

function php_scgi_client__get_post_data() {
    global $php_scgi_client__post_data_cache;
    
    if($php_scgi_client__post_data_cache === NULL) {
        $php_scgi_client__post_data_cache = file_get_contents("php://input");
    }
    
    return $php_scgi_client__post_data_cache;
}

function php_scgi_client__get_conf() {
    $conf_file = dirname(__FILE__).'/php_scgi_client_conf.php';
    
    if(file_exists($conf_file)) {
        require_once $conf_file;
        
        return $PHP_SCGI_CLIENT_CONF__CONF;
    } else {
        throw new php_scgi_client__error($conf_file.': Configuration file is not found');
    }
}

function php_scgi_client__fsockopen_or_error() {
    $conf = php_scgi_client__get_conf();
    $socket_file = $conf['SOCKET_FILE'];
    
    $fd = @fsockopen($socket_file);
    
    if($fd !== FALSE && $fd !== NULL) {
        return $fd;
    } else {
        @fclose($fd);
        
        throw new php_scgi_client__error($socket_file.': Can\'t connect to socket file');
    }
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

function php_scgi_client__main() {
    try {
        @header('Content-Type: text/plain;charset=utf-8'); // TEST
        
        $TEST_TIME = time();
        echo 'BEGIN-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        $fd = php_scgi_client__fsockopen_or_error();
        
        $output = php_scgi_client__format_output();
        $output_len = strlen($output);
        
         echo '000-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        for($all_written = 0; $all_written < $output_len; $all_written += $written) {
            $written = @fwrite($fd, substr($output, $all_written));
            if($written === FALSE || $written === NULL) {
                break;
            }
        }
        
        echo '111-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        @fflush($fd);
        
        echo '222-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        for(;;) {
            if(!feof($fd)) {
                $raw_header = @fgets($fd);
            } else {
                break;
            }
            
            if($raw_header !== FALSE && $raw_header !== NULL) {
                $header = trim($raw_header);
                
                if($header) {
                    header($header);
                } else {
                    break;
                }
            } else {
                break;
            }
        }
        
        echo '333-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        for(;;) {
            if(!feof($fd)) {
                $data = @fread($fd, 8192);
            } else {
                break;
            }
            
            if($data !== FALSE && $data !== NULL) {
                echo $data;
            } else {
                break;
            }
        }
        
        echo '444-'.abs(time() - $TEST_TIME).'; '; // TEST
        
        @fclose($fd);
        
        echo 'END-'.abs(time() - $TEST_TIME).'; '; // TEST
    } catch(php_scgi_client__error $e) {
        @header('Content-Type: text/plain;charset=utf-8');
        
        echo 'Error: '.$e->getMessage();
    }
}
