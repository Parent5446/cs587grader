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

use CS587Grader\SubmissionBundle\Entity\Grade;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CS587Grader\SubmissionBundle\Entity\Assignment;
use Doctrine\ORM\Query\Expr;

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
		/** @var \Doctrine\ORM\EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		$res = $em->createQueryBuilder()
			->select( 'a', 'g' )
			->from( 'CS587GraderSubmissionBundle:Assignment', 'a' )
			->leftJoin( 'a.submissions', 'g', Expr\Join::WITH, 'g.user = :user' )
			->orderBy( 'a.dueDate' )
			->setParameter( 'user', $this->getUser() )

			->getQuery()
			->getResult();


		return $this->render(
			'CS587GraderSubmissionBundle:Default:index.html.twig',
			[ 'assignments' => $res ]
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
			->add( 'description', 'text', [ 'required' => false ] )
			->add( 'dueDate', 'datetime' )
			->add( 'save', 'submit' )
			->add( $name ? 'delete' : 'cancel', 'submit' )
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
				if ( $job ) {
					$em->remove( $job );
				}
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

	/**
	 * List the submissions for an assignment
	 *
	 * @param Assignment $assignment
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function listGradesAction( Assignment $assignment ) {
		return $this->render(
			'CS587GraderSubmissionBundle:Default:listGrades.html.twig',
			[ 'assignment' => $assignment ]
		);
	}

	/**
	 * Edit a grade for a user
	 *
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
					'cs587_grader_submission_grade_list',
					[ 'name' => $grade->getAssignment()->getName() ]
				) );
		}

		return $this->render(
			'CS587GraderSubmissionBundle:Default:editGrade.html.twig',
			[ 'grade' => $grade, 'form' => $form->createView() ]
		);
	}
}
