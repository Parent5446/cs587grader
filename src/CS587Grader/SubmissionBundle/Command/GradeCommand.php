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
use Symfony\Component\Filesystem\Filesystem;

/**
 * Command to calculate a preliminary grade for an assignment
 *
 * @package CS587Grader\SubmissionBundle\Command
 */
class GradeCommand extends DoctrineCommand
{
	/**
	 * CFLAGS to be passed to compiler
	 */
	const CFLAGS = '-Wall -Werror';

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

		// Remove existing grade
		$grade = $this->getEntityManager( null )->getRepository( 'CS587GraderSubmissionBundle:Grade' )
			->findOneBy( [ 'user' => $user, 'assignment' => $assignment ] );
		if ( $grade ) {
			$em->remove( $grade );
			$em->flush();
		}

		$grade = $this->grade( $user, $assignment, $input->getArgument( 'commit' ) );
		$em->persist( $grade );
		$em->flush();
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
	 * @throws \RuntimeException if working directory cannot be made
	 * @return Grade
	 */
	private function grade( User $user, Assignment $assignment, $commit ) {
		$fs = new Filesystem();
		$di = $this->getContainer();
		$client = $user->getBitbucketClient(
			$di->getParameter( 'bitbucket_id' ),
			$di->getParameter( 'bitbucket_secret' )
		);

		// Setup temporary directory
		$uploadsDir = $di->getParameter( 'kernel.root_dir' ) . DIRECTORY_SEPARATOR . 'uploads';
		$workingDir = $uploadsDir . DIRECTORY_SEPARATOR . 'tmp';
		$repoDir = $workingDir . DIRECTORY_SEPARATOR
			. $user->getUsername() . '-' . $user->getRepository() . '-' . substr( $commit, 0, 12 );
		$fs->mkdir( $workingDir, 0700 );

		$key = "{$user->getUsername()}-{$assignment->getName()}";
		$filename = "$uploadsDir/$key.tar.gz";

		// Fetch the tarball for the commit
		try {
			$client->get(
				"https://bitbucket.org/{$user->getUsername()}/{$user->getRepository()}/get/$commit.tar.gz",
				[ 'save_to' => $filename ]
			);
		} catch ( ClientException $e ) {
			return new Grade( $assignment, $user, null, 'Download Error' );
		}

		// Unzip the tarball into temp directory
		$gzip = proc_open( 'tar xzf ' . escapeshellarg( $filename ), [], $pipes, $workingDir );
		$status = proc_close( $gzip );
		if ( $status !== 0 ) {
			throw new \RuntimeException( 'Could not extract tarball.' );
		}

		// Compile
		$make = proc_open(
			'make CFLAGS=' . escapeshellarg( self::CFLAGS ),
			[
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes,
			$repoDir,
			null,
			[
				'suppress_errors' => true,
				'bypass_shell' => true,
			]
		);

		if ( !is_resource( $make ) ) {
			throw new \RuntimeException( 'Could not launch compilation process' );
		}

		// Extract all stdout and stderr
		$output = '';
		$error = '';
		while ( !feof( $pipes[1] ) ) {
			$output .= stream_get_contents( $pipes[1] );
		}
		while ( !feof( $pipes[2] ) ) {
			$error .= stream_get_contents( $pipes[2] );
		}

		// Wait for termination
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $make );

		// Check for compile failure
		/** @var Grade $grade */
		$grade = null;
		if ( $status !== 0 ) {
			$grade = new Grade( $assignment, $user, 0, 'Compilation Failed' );
			$grade->setFileKey( $key );
			$grade->setGradeExtendedReason( "$output\n$error" );
		} else {
			$grade = new Grade( $assignment, $user, null, 'Awaiting Review' );
			$grade->setFileKey( $key );
		}

		// Cleanup
		$fs->remove( $workingDir );

		return $grade;
	}
}
