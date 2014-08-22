<?php
/**
 * This file is part of CS587Grader.
 *
 * Copyright (c) 2014 Tyler Romeo
 *
 * CS587Grader is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * CS587Grader is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with CS587Grader.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author Tyler Romeo <tylerromeo@gmail.com>
 * @copyright 2013 Tyler Romeo
 * @license https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Publi\
 * c License
 */

use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

// If you don't want to setup permissions the proper way, just uncomment the following PHP line
// read http://symfony.com/doc/current/book/installation.html#configuration-and-setup
// for more information
//umask(0000);

// This check prevents access to debug front controllers that are deployed by accident
// to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
if ( isset( $_SERVER[ 'HTTP_CLIENT_IP' ] )
	|| isset( $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] )
	|| !( in_array( $_SERVER[ 'REMOTE_ADDR' ], [ '127.0.0.1', 'fe80::1', '::1' ] )
		|| PHP_SAPI === 'cli-server' )
) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit( 'You are not allowed to access this file. Check '
		. basename( __FILE__ ) . ' for more information.' );
}

$loader = require_once __DIR__ . '/../app/bootstrap.php.cache';
Debug::enable();

require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel( 'dev', true );
$kernel->loadClassCache();
$request = Request::createFromGlobals();
$response = $kernel->handle( $request );
$response->send();
$kernel->terminate( $request, $response );
