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

use CS585Grader\AccountBundle\Entity\User;
use CS585Grader\SubmissionBundle\Entity\Assignment;
use CS585Grader\SubmissionBundle\Entity\Grade;
use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Command to calculate a preliminary grade for an assignment
 *
 * @package CS585Grader\SubmissionBundle\Command
 */
class GradeCommand extends DoctrineCommand
{
	/**
	 * CFLAGS to be passed to compiler
	 */
	const CFLAGS = '-Wall -Werror';

	protected function configure() {
		$this
			->setName( 'cs585:grade' )
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
		$user = $em->getRepository( 'CS585GraderAccountBundle:User' )
			->findOneBy( [ 'username' => $input->getArgument( 'user' ) ] );
		/** @var Assignment $assignment */
		$assignment = $em->getRepository( 'CS585GraderSubmissionBundle:Assignment' )
			->findOneBy( [ 'name' => $input->getArgument( 'assignment' ) ] );

		/** @var Grade $grade */
		$grade = $this->getEntityManager( null )->getRepository( 'CS585GraderSubmissionBundle:Grade' )
			->findOneBy( [ 'user' => $user, 'assignment' => $assignment ] );
		if ( !$grade ) {
			$grade = new Grade( $assignment, $user, null );
			$em->persist( $grade );
		}

		$this->grade( $grade, $input->getArgument( 'commit' ) );

		$em->flush();
	}

	/**
	 * Create a new Grade entity for the given assignment
	 *
	 * Download an assignment and attempt to compile it on a remote
	 * VM. If it fails in any way, give a zero. Otherwise, leave it
	 * ungraded.
	 *
	 * @param \CS585Grader\SubmissionBundle\Entity\Grade $grade
	 * @param string $commit
	 *
	 * @throws \RuntimeException if working directory cannot be made
	 */
	private function grade( Grade $grade, $commit ) {
		$fs = new Filesystem();

		if ( !is_object( $grade->getFile() ) ) {
			$this->downloadFromBitbucket( $grade, $commit );
		}

		// Setup temporary directory
		$uploadsDir = $this->getContainer()->getParameter( 'cs585grader.submission.uploaddir' );
		$workingDir = $uploadsDir . DIRECTORY_SEPARATOR . 'tmp';
		$fs->mkdir( $workingDir, 0700 );

		// Unzip the tarball into temp directory
		$gzip = proc_open(
			'tar xzf ' . escapeshellarg( $grade->getFile()->getPathname() ),
			[], $pipes, $workingDir
		);
		$status = proc_close( $gzip );
		if ( $status !== 0 ) {
			$grade->setGradeReason( 'Extraction Error' );

			return;
		}

		$repoDir = $workingDir;
		// If Makefile is not in root dir, the repository might have been put into
		// a top-level directory. Try changing into first directory and go from there
		if ( !file_exists( "$repoDir/Makefile" ) ) {
			/** @var \DirectoryIterator $fileInfo */
			foreach ( new \DirectoryIterator( $workingDir ) as $fileInfo ) {
				if ( $fileInfo->isDir() && !$fileInfo->isDot() ) {
					$repoDir = $fileInfo->getPathname();
				}
			}
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
			$grade->setGradeReason( 'Internal Compilation Error' );

			return;
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
		if ( $status !== 0 ) {
			$grade->setGrade( 0 );
			$grade->setGradeReason( 'Compilation Failed' );
		} else {
			$grade->setGrade( null );
			$grade->setGradeReason( 'Awaiting Review' );
		}
		$grade->setGradeExtendedReason( "$error\n$output" );

		// Cleanup
		$fs->remove( $workingDir );
	}

	/**
	 * Download an assignment from BitBucket
	 *
	 * @param Grade $grade Grade to retrieve for
	 * @param string $commit Commit to download
	 */
	private function  downloadFromBitbucket( Grade $grade, $commit ) {
		$di = $this->getContainer();
		$user = $grade->getUser();
		$client = $user->getBitbucketClient(
			$di->getParameter( 'bitbucket_id' ),
			$di->getParameter( 'bitbucket_secret' )
		);

		$filename = $di->getParameter( 'cs585grader.submission.uploaddir' )
			. '/' . $user->getUsername() . '-' . $grade->getAssignment()->getName() . '.tar.gz';

		// Fetch the tarball for the commit
		try {
			$client->get(
				"https://bitbucket.org/{$user->getUsername()}/{$user->getRepository()}/get/$commit.tar.gz",
				[ 'save_to' => $filename ]
			);
		} catch ( ClientException $e ) {
			return;
		}

		$grade->setFile( new File( $filename ) );
	}
}
