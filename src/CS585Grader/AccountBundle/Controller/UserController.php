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

namespace CS585Grader\AccountBundle\Controller;

use CS585Grader\AccountBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller that allows manipulation of users
 *
 * @package CS585Grader\AccountBundle\Controller
 */
class UserController extends Controller {
	/**
	 * List the registered users
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function listAction() {
		return $this->render( 'CS585GraderAccountBundle:User:list.html.twig',
			[ 'users' =>
				$this->getDoctrine()->getRepository( 'CS585GraderAccountBundle:User' )->findAll() ] );
	}

	/**
	 * Delete a user, providing a form to confirm
	 *
	 * @param Request $request
	 * @param User $user
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function deleteAction( Request $request, User $user ) {
		$form = $this->createFormBuilder( $user )
			->add( 'delete', 'submit' )
			->getForm();

		$form->handleRequest( $request );
		if ( $form->isValid() ) {
			$em = $this->getDoctrine()->getManager();
			$em->remove( $user );
			$em->flush();

			return $this->redirect( $this->generateUrl( 'cs585_grader_account_userlist' ) );
		}

		return $this->render( 'CS585GraderAccountBundle:User:delete.html.twig',
			[ 'user' => $user, 'form' => $form->createView() ]
		);
	}
}
