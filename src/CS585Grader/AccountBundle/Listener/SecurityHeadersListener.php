<?php
/**
 * This file is part of CS585Grader.
 *
 * Copyright (c) 2014 Tyler Romeo
 *
 * CS585Grader is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * CS585Grader is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with CS585Grader.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author Tyler Romeo <tylerromeo@gmail.com>
 * @copyright 2013 Tyler Romeo
 * @license https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Publi\
c License
 */

namespace CS585Grader\AccountBundle\Listener;


use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Listen for the response event and apply security headers
 *
 * @package CS585Grader\AccountBundle\Listener
 */
class SecurityHeadersListener {
	/**
	 * Add security headers such as CSP depending on kernel debug mode
	 *
	 * @param FilterResponseEvent $event
	 */
	public function onKernelResponse( FilterResponseEvent $event ) {
		$responseHeaders = $event->getResponse()->headers;

		$responseHeaders->set( 'X-XSS-Protection', '1; mode=block' );
		$responseHeaders->set( 'X-Permitted-Cross-Domain-Policies', 'none' );
	}
}
