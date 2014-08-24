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
 * @license https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License
 */

namespace CS587Grader\SubmissionBundle\Controller;

use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CS587Grader\SubmissionBundle\Entity\Assignment;

/**
 * Main controller that provides logic for submitting assignments
 *
 * @package CS587Grader\SubmissionBundle\Controller
 */
class DefaultController extends Controller
{
	/**
	 * Get the home page
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function indexAction() {
		return $this->render(
			'CS587GraderSubmissionBundle:Default:index.html.twig',
			[]
		);
	}

	/**
	 * Shows list of assignments
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function adminAction() {
		$assignments = $this->getDoctrine()
			->getRepository( 'CS587GraderSubmissionBundle:Assignment' )
			->findAll();

		return $this->render(
			'CS587GraderSubmissionBundle:Default:admin.html.twig',
			[ 'assignments' => $assignments ]
		);
	}

	/**
	 * Allows editing or making a new assignment
	 *
	 * @param Request $request
	 * @param string $name
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function editAction( Request $request, $name = '' ) {
		/** @var Assignment $assignment */
		$assignment = null;
		if ( $name ) {
			$assignment = $this->getDoctrine()->getRepository( 'CS587GraderSubmissionBundle:Assignment' )
				->findOneBy( [ 'name' => $name ] );
		}

		if ( !$assignment ) {
			$assignment = new Assignment( $name );
		}

		$form = $this->createFormBuilder( $assignment )
			->add( 'name', 'text' )
			->add( 'description', 'text' )
			->add( 'dueDate', 'datetime' )
			->add( 'save', 'submit' )
			->add( 'delete', 'submit' )
			->getForm();

		$form->handleRequest( $request );
		// Process a submitted form
		if ( $form->isValid() ) {
			$em = $this->getDoctrine()->getManager();
			$button = $form->getClickedButton();
			/** @var Job $job */
			$job = null;

			// Fetch the related job, if it exists
			if ( $name ) {
				/** @var \JMS\JobQueueBundle\Entity\Repository\JobRepository $jobRepo */
				$jobRepo = $em->getRepository( 'JMSJobQueueBundle:Job' );
				$job = $jobRepo->findJobForRelatedEntity( 'cs587:collect', $assignment );
			}

			if ( $button->getName() === 'save' ) {
				// User wants to save

				// Persist assignment if it is a new one
				if ( !$name ) {
					$em->persist( $assignment );
				}

				// Make a job if one does not exist, and persist it
				if ( !$job ) {
					$job = new Job( 'cs587:collect', [ $assignment->getName() ] );
					$job->addRelatedEntity( $assignment );
					$em->persist( $job );
				}
				$job->setExecuteAfter( $assignment->getDueDate() );
			} elseif ( $button->getName() === 'delete' ) {
				// User wants to delete
				$em->remove( $job );
				$em->remove( $assignment );
			}

			$em->flush();

			return $this->redirect( $this->generateUrl( 'cs587_grader_submission_admin' ) );
		}

		return $this->render(
			'CS587GraderSubmissionBundle:Default:edit.html.twig',
			[ 'form' => $form->createView() ]
		);
	}
}
