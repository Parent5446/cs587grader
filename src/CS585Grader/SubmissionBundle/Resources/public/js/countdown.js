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
 * @license https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License
 */

( function ( $ ) {
    'use strict';

    function updateCountdowns() {
        $( '.cs587-countdown' ).each( function () {
            var value = $( this ),
                data = value.data(),
                dueDate = moment( data.duedate ),
                timeRemaining = dueDate.fromNow();

            value.text( timeRemaining );
        } );
    }

    $( document ).ready( function () {
        setInterval( updateCountdowns, 1000 );
    } );
}( jQuery ) );
