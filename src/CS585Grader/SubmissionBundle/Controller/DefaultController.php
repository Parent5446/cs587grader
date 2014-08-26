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
 * @license https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License
 */

namespace CS585Grader\SubmissionBundle\Controller;

use CS585Grader\SubmissionBundle\Entity\Grade;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CS585Grader\SubmissionBundle\Entity\Assignment;
use Doctrine\ORM\Query\Expr;

/**
 * Main controller that provides logic for submitting assignments
 *
 * @package CS585Grader\SubmissionBundle\Controller
 */
class DefaultController extends Controller
{
	/**
	 * Get the home page
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function indexAction() {
		/** @var \Doctrine\ORM\EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		$res = $em->createQueryBuilder()
			->select( 'a', 'g' )
			->from( 'CS585GraderSubmissionBundle:Assignment', 'a' )
			->leftJoin( 'a.submissions', 'g', Expr\Join::WITH, 'g.user = :user' )
			->orderBy( 'a.dueDate' )
			->setParameter( 'user', $this->getUser() )

			->getQuery()
			->getResult();

		return $this->render(
			'CS585GraderSubmissionBundle:Default:index.html.twig',
			[ 'assignments' => $res ]
		);
	}

	/**
	 * Action for users to manually submit their assignments
	 *
	 * @param Request $request
	 * @param Assignment $assignment
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function submitAction( Request $request, Assignment $assignment ) {
		$grade = new Grade( $assignment, $this->getUser(), 'Submitted' );
		$form = $this->createFormBuilder( $grade )
			->add( 'file', 'cs587_submission', [ 'assignment' => $assignment, 'user' => $this->getUser() ] )
			->add( 'submit', 'submit' )
			->getForm();

		$form->handleRequest( $request );
		if ( $form->isValid() ) {
			$grade->setFile( $form->get( 'file' )->getData() );
			$em = $this->getDoctrine()->getManager();
			$em->persist( $grade );
			$em->flush();

			return $this->redirect( $this->generateUrl( 'cs585_grader_submission_homepage' ) );
		}

		return $this->render(
			'CS585GraderSubmissionBundle:Default:submit.html.twig',
			[ 'form' => $form->createView() ]
		);
	}
}
