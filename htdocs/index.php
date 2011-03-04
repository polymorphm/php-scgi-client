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

if (!ini_get('display_errors')) {
    ini_set('display_errors', 1);
}
error_reporting(E_ALL);

require_once dirname(__FILE__).'/../php_scgi_client.php';
php_scgi_client__main();
