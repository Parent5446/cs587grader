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
 * Controller that allows changing custom attributes of the user's profile
 *
 * @package CS585Grader\AccountBundle\Controller
 */
class RepositorySelectionController extends Controller
{
	/**
	 * Let the user set which repository to submit assignments from
	 *
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @throws \LogicException
	 */
	public function indexAction( Request $request ) {
		/** @var User $user */
		$user = $this->getUser();
		if ( !$user instanceof User ) {
			throw new \LogicException( 'Invalid user type.' );
		}

		// Build the form
		$formBuilder = $this->createFormBuilder( $user );

		// Get list of repos from bitbucket
		$res = $user
			->getBitbucketClient(
				$this->container->getParameter( 'bitbucket_id' ),
				$this->container->getParameter( 'bitbucket_secret' )
			)
			->get( 'user' )
			->json();

		$repos = [];
		foreach ( $res['repositories'] as $repo ) {
			$repoName = "{$repo['owner']}/{$repo['name']}";
			$repos[$repoName] = $repoName;
		}

		$formBuilder->add( 'repository', 'choice', [
				'label' => ' ',
				'choices' => $repos,
				'empty_value' => 'None',
			] );

		$formBuilder->add( 'save', 'submit', [ 'label' => 'Save' ] );
		$form = $formBuilder->getForm();

		// Process submitted form
		$form->handleRequest( $request );
		if ( $form->isValid() ) {
			$this->getDoctrine()->getManager()->flush();

			return $this->redirect( $this->generateUrl( 'fos_user_profile_show' ) );
		}

		// Otherwise, show form
		return $this->render(
			'CS585GraderAccountBundle:RepositorySelection:index.html.twig',
			[ 'form' => $form->createView() ]
		);
	}
}
