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


use CS585Grader\SubmissionBundle\Entity\Assignment;
use CS585Grader\SubmissionBundle\Entity\Grade;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Admin-related functions
 *
 * @package CS585Grader\SubmissionBundle\Controller
 */
class AdminController extends Controller {

	/**
	 * Shows list of assignments
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function adminAction() {
		$assignments = $this->getDoctrine()
			->getRepository( 'CS585GraderSubmissionBundle:Assignment' )
			->findAll();

		return $this->render(
			'CS585GraderSubmissionBundle:Default:admin.html.twig',
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
		if ( $name !== 'new' ) {
			$assignment = $this->getDoctrine()->getRepository( 'CS585GraderSubmissionBundle:Assignment' )
				->findOneBy( [ 'name' => $name ] );
		}

		if ( !$assignment ) {
			$assignment = new Assignment( $name !== 'new' ? $name : '' );
		}

		$form = $this->createFormBuilder( $assignment )
			->add( 'name', 'text' )
			->add( 'description', 'text', [ 'required' => false ] )
			->add( 'dueDate', 'datetime' )
			->add( 'save', 'submit' )
			->add( $name !== 'new' ? 'delete' : 'cancel', 'submit' )
			->getForm();

		$form->handleRequest( $request );
		// Process a submitted form
		if ( $form->isValid() ) {
			$em = $this->getDoctrine()->getManager();
			$button = $form->getClickedButton();
			/** @var Job $job */
			$job = null;

			// Fetch the related job, if it exists
			if ( $name !== 'new' ) {
				/** @var \JMS\JobQueueBundle\Entity\Repository\JobRepository $jobRepo */
				$jobRepo = $em->getRepository( 'JMSJobQueueBundle:Job' );
				$job = $jobRepo->findJobForRelatedEntity( 'cs585:collect', $assignment );
			}

			if ( $button->getName() === 'save' ) {
				// User wants to save

				// Persist assignment if it is a new one
				if ( $name === 'new' ) {
					$em->persist( $assignment );
				}

				// Make a job if one does not exist, and persist it
				if ( !$job ) {
					$job = new Job( 'cs585:collect', [ $assignment->getName() ] );
					$job->addRelatedEntity( $assignment );
					$em->persist( $job );
				}
				$job->setExecuteAfter( $assignment->getDueDate() );
			} elseif ( $button->getName() === 'delete' ) {
				// User wants to delete
				if ( $job ) {
					$em->remove( $job );
				}
				$em->remove( $assignment );
			}

			$em->flush();

			return $this->redirect( $this->generateUrl( 'cs585_grader_submission_admin' ) );
		}

		return $this->render(
			'CS585GraderSubmissionBundle:Default:edit.html.twig',
			[ 'form' => $form->createView() ]
		);
	}

	/**
	 * List the submissions for an assignment
	 *
	 * @param Assignment $assignment
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function listGradesAction( Assignment $assignment ) {
		return $this->render(
			'CS585GraderSubmissionBundle:Default:listGrades.html.twig',
			[ 'assignment' => $assignment ]
		);
	}

	/**
	 * Edit a grade for a user
	 *
	 * @param \Symfony\Component\HttpFoundation\Request $request
	 * @param Grade $grade
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function editGradeAction( Request $request, Grade $grade ) {
		$form = $this->createFormBuilder( $grade )
			->add( 'grade', 'integer', [ 'precision' => 0 ] )
			->add( 'gradeReason', 'text' )
			->add( 'gradeExtendedReason', 'textarea', [ 'required' => false ] )
			->add( 'save', 'submit' )
			->getForm();

		$form->handleRequest( $request );
		if ( $form->isValid() ) {
			$this->getDoctrine()->getManager()->flush();

			return $this->redirect(
				$this->generateUrl(
					'cs585_grader_submission_grade_list',
					[ 'name' => $grade->getAssignment()->getName() ]
				) );
		}

		return $this->render(
			'CS585GraderSubmissionBundle:Default:editGrade.html.twig',
			[ 'grade' => $grade, 'form' => $form->createView() ]
		);
	}
}
