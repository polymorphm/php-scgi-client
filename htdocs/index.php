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

if (!ini_get('display_errors')) {
    ini_set('display_errors', 1);
}
error_reporting(E_ALL);

global $PHP_SCGI_CLIENT__CONF_PATH;
$PHP_SCGI_CLIENT__CONF_PATH = dirname(__FILE__).'/../php_scgi_client_conf.php';

require_once dirname(__FILE__).'/../php_scgi_client.php';
php_scgi_client__main();
