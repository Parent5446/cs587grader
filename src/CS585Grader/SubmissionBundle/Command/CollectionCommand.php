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

namespace CS585Grader\SubmissionBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use GuzzleHttp\Exception\ClientException;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CS585Grader\AccountBundle\Entity\User;
use CS585Grader\SubmissionBundle\Entity\Assignment;
use CS585Grader\SubmissionBundle\Entity\Grade;

/**
 * Command to collect all assignments and initiate jobs for grading
 *
 * @package CS585Grader\SubmissionBundle\Command
 */
class CollectionCommand extends DoctrineCommand {

	protected function configure() {
		$this
			->setName( 'cs585:collect' )
			->setDescription( 'Download and auto-grade a student\'s application' )
			->addArgument( 'assignment', InputArgument::REQUIRED, 'Name of assignment to grade' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$em = $this->getEntityManager( null );
		$gradeRepo = $em->getRepository( 'CS585GraderSubmissionBundle:Grade' );

		/** @var User[] $users */
		$users = $em->getRepository( 'CS585GraderAccountBundle:User' )->findAll();
		/** @var Assignment $assignment */
		$assignment = $em->getRepository( 'CS585GraderSubmissionBundle:Assignment' )
			->findOneBy( [ 'name' => $input->getArgument( 'assignment' ) ] );

		foreach ( $users as $user ) {
			// Remove existing grade
			/** @var Grade $grade */
			$grade = $gradeRepo->findOneBy( [ 'user' => $user, 'assignment' => $assignment ] );
			// Remove previous bad grades
			if ( $grade && !$grade->getFileKey() && !$grade->getGrade() ) {
				$em->remove( $grade );
				$em->flush();
			}

			$this->collect( $assignment, $user );
		}

		$em->flush();
	}

	/**
	 * Get the commit for a given user and trigger a job to grade it
	 *
	 * @param Assignment $assignment
	 * @param User $user
	 *
	 * @return null
	 */
	private function collect( Assignment $assignment, User $user ) {
		$di = $this->getContainer();
		$client = $user->getBitbucketClient(
			$di->getParameter( 'bitbucket_id' ),
			$di->getParameter( 'bitbucket_secret' )
		);

		// Get the commit associated with the assignment tag
		try {
			$res = $client->get(
				"repositories/{$user->getUsername()}/{$user->getRepository()}/branches-tags" );
		} catch ( ClientException $e ) {
			return $this->assignZero( $assignment, $user, 'Non-existent Repository' );
		}

		$res = $res->json();
		if ( !isset( $res['tags'] ) ) {
			return $this->assignZero( $assignment, $user, 'Missing Assignment Tag' );
		}

		$commit = null;
		foreach ( $res['tags'] as $tag ) {
			if ( $tag['name'] === $assignment->getName() ) {
				$commit = $tag['changeset'];
			}
		}

		if ( $commit === null ) {
			return $this->assignZero( $assignment, $user, 'Missing Assignment Tag' );
		}

		$em = $this->getEntityManager( null );
		$job = new Job( 'cs585:grade', [ $assignment->getName(), $user->getUsername(), $commit ] );
		$job->addRelatedEntity( $user );
		$job->addRelatedEntity( $assignment );
		$em->persist( $job );

		return null;
	}

	/**
	 * Assign a zero to a student for the assignment
	 *
	 * @param Assignment $assignment
	 * @param User $user
	 * @param string $message
	 *
	 * @return null
	 */
	private function assignZero( Assignment $assignment, User $user, $message ) {
		$grade = new Grade( $assignment, $user, 0, $message );

		$em = $this->getEntityManager( null );
		$em->persist( $grade );

		return null;
	}
}
