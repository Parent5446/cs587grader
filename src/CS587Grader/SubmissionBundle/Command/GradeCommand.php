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
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CS587Grader\AccountBundle\Entity\User;
use CS587Grader\SubmissionBundle\Entity\Assignment;
use CS587Grader\SubmissionBundle\Entity\Grade;

/**
 * Command to calculate a preliminary grade for an assignment
 *
 * @package CS587Grader\SubmissionBundle\Command
 */
class GradeCommand extends DoctrineCommand
{
	/** @var \Aws\S3\S3Client */
	private $s3;

	protected function configure() {
		$this
			->setName( 'cs587:grade' )
			->setDescription( 'Download and auto-grade a student\'s application' )
			->addArgument( 'assignment', InputArgument::REQUIRED, 'Name of assignment to grade' )
			->addArgument( 'user', InputArgument::REQUIRED, 'Name of student' )
			->addArgument( 'commit', InputArgument::REQUIRED, 'Commit to collect' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$em = $this->getEntityManager( null );

		/** @var User $user */
		$user = $em->getRepository( 'CS587GraderAccountBundle:User' )
			->findOneBy( [ 'username' => $input->getArgument( 'user' ) ] );
		/** @var Assignment $assignment */
		$assignment = $em->getRepository( 'CS587GraderSubmissionBundle:Assignment' )
			->findOneBy( [ 'name' => $input->getArgument( 'assignment' ) ] );

		$grade = $this->grade( $user, $assignment, $input->getArgument( 'commit' ) );
		$em->persist( $grade );
	}

	/**
	 * Create a new Grade entity for the given assignment
	 *
	 * Download an assignment and attempt to compile it on a remote
	 * VM. If it fails in any way, give a zero. Otherwise, leave it
	 * ungraded.
	 *
	 * @param User $user
	 * @param Assignment $assignment
	 * @param string $commit
	 *
	 * @return Grade
	 */
	private function grade( User $user, Assignment $assignment, $commit ) {
		$di = $this->getContainer();
		$client = $user->getBitbucketClient(
			$di->getParameter( 'bitbucket_id' ),
			$di->getParameter( 'bitbucket_secret' )
		);

		// Fetch the tarball for the commit
		try {
			$res = $client->get( "https://bitbucket.org/{$user->getRepository()}/get/$commit.tar.gz" );
		} catch ( ClientException $e ) {
			return new Grade( $assignment, $user, null, 'grade-downloaderror' );
		}
		$body = $res->getBody();
		$bodyLength = $body->getSize();
		$body = $body->detach();

		// Prep the Grade entity
		$grade = new Grade( $assignment, $user, null );
		$key = "{$user->getUsername()}/{$assignment->getName()}.tar.gz";
		$grade->setFileKey( $key );

		// Store the file in S3 for later use
		$this->s3->putObject( [
				'ACL' => 'authenticated-read',
				'Body' => $body,
				'Bucket' => $di->getParameter( 'aws_s3_bucket' ),
				'CacheControl' => 'string',
				'ContentDisposition' => '{$user->getUsername()}-{$assignment->getName()}.tar.gz',
				'ContentLength' => $bodyLength,
				'ContentType' => 'application/x-gzip',
				'Key' => $key,
			] );

		return $grade;
	}
}
