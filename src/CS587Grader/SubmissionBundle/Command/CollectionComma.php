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
c License
 */

namespace CS587Grader\SubmissionBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CS587Grader\AccountBundle\Entity\User;
use CS587Grader\SubmissionBundle\Entity\Assignment;
use CS587Grader\SubmissionBundle\Entity\Grade;

/**
 * Command to collect all assignments and initiate jobs for grading
 *
 * @package CS587Grader\SubmissionBundle\Command
 */
class CollectionCommand extends DoctrineCommand {

	protected function configure() {
		$this
			->setName( 'cs587:collect' )
			->setDescription( 'Download and auto-grade a student\'s application' )
			->addArgument( 'assignment', InputArgument::REQUIRED, 'Name of assignment to grade' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$em = $this->getEntityManager( '' );

		/** @var User[] $users */
		$users = $em->getRepository( 'CS587GraderAccountBundle:User' )->findAll();
		/** @var Assignment $assignment */
		$assignment = $em->getRepository( 'CS587GraderSubmissionBundle:Assignment' )
			->findOneBy( [ 'name' => $input->getArgument( 'assignment' ) ] );

		foreach ( $users as $user ) {
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
		$res = $client->get( "repositories/{$user->getRepository()}/branches-tags" );
		if ( $res->getStatusCode() !== 200 ) {
			return $this->assignZero( $assignment, $user, 'grade-norepo' );
		}

		$res = $res->json();
		if ( !isset( $res['tags'] ) ) {
			return $this->assignZero( $assignment, $user, 'grade-nosubmission' );
		}

		$commit = null;
		foreach ( $res['tags'] as $tag ) {
			if ( $tag['name'] === $assignment->getName() ) {
				$commit = $tag['changeset'];
			}
		}

		if ( $commit === null ) {
			return $this->assignZero( $assignment, $user, 'grade-nosubmission' );
		}

		$em = $this->getEntityManager( '' );
		$job = new Job( 'cs587:grade', [ $assignment->getName(), $user->getUsername(), $commit ] );
		$job->addRelatedEntity( $user );
		$job->addRelatedEntity( $assignment );
		$em->flush();

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

		$em = $this->getEntityManager( '' );
		$em->persist( $grade );

		return null;
	}
}
