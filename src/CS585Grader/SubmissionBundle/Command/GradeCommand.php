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

	/** @var OutputInterface Output to be used */
	private $output;

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
		$this->output = $output;

		/** @var User $user */
		$user = $em->getRepository( 'CS585GraderAccountBundle:User' )
			->findOneBy( [ 'username' => $input->getArgument( 'user' ) ] );
		/** @var Assignment $assignment */
		$assignment = $em->getRepository( 'CS585GraderSubmissionBundle:Assignment' )
			->findOneBy( [ 'name' => $input->getArgument( 'assignment' ) ] );

		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
			$this->output->writeln( "Grading assign {$assignment->getName()} for {$user->getUsername()}" );
		}

		/** @var Grade $grade */
		$grade = $this->getEntityManager( null )->getRepository( 'CS585GraderSubmissionBundle:Grade' )
			->findOneBy( [ 'user' => $user, 'assignment' => $assignment ] );
		if ( !$grade ) {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
				$this->output->writeln( "Grade does not exist yet. Making a new one." );
			}
			$grade = new Grade( $assignment, $user, null );
		}

		$this->grade( $grade, $input->getArgument( 'commit' ) );

		$em->persist( $grade );
		$em->flush();
		$this->output = null;
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
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
				$this->output->writeln( "File does not exist yet. Downloading from BitBucket." );
			}
			$this->downloadFromBitbucket( $grade, $commit );
		}

		// Setup temporary directory
		$uploadsDir = $this->getContainer()->getParameter( 'cs585grader.submission.uploaddir' );
		$workingDir = $uploadsDir . DIRECTORY_SEPARATOR
			. 'tmp_' . $grade->getUser()->getUsername() . '_' . $grade->getAssignment()->getName();
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
			$this->output->writeln( "Making temporary directory: $workingDir." );
		}
		$fs->mkdir( $workingDir, 0700 );

		// Unzip the tarball into temp directory
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
			$this->output->writeln( "Extracting file {$grade->getFile()->getPathname()} into $workingDir." );
		}
		$pipes = [];
		$gzip = proc_open(
			'tar xzf ' . escapeshellarg( $grade->getFile()->getPathname() ),
			[
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes,
			$workingDir
		);

		$output = '';
		$error = '';
		while ( !feof( $pipes[1] ) ) {
			$output .= stream_get_contents( $pipes[1] );
		}
		while ( !feof( $pipes[2] ) ) {
			$error .= stream_get_contents( $pipes[2] );
		}

		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE ) {
			$this->output->write( $output );
		}
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
			$this->output->write( $error );
		}

		$status = proc_close( $gzip );
		if ( $status !== 0 ) {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
				$this->output->writeln( 'Extraction Error' );
			}

			$grade->setGradeReason( 'Extraction Error' );
			$grade->setGradeExtendedReason( "$error\n$output" );

			return;
		}

		// Search for a Makefil in the project
		$repoDir = null;

		$fileIterator = new \RecursiveDirectoryIterator( $workingDir );
		$iterIterator = new \RecursiveIteratorIterator( $fileIterator );
		$makefileIterator = new \RegexIterator( $iterIterator, '!/Makefile$!i' );

		/** @var \DirectoryIterator $fileInfo */
		foreach ( $makefileIterator as $pathname => $fileInfo ) {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
				$this->output->writeln( "Found Makefile at {$pathname}" );
			}
			if ( $repoDir === null || strlen( $fileInfo->getPath() ) < strlen( $repoDir ) ) {
				$repoDir = $fileInfo->getPath();
			}
		}

		// Compile
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
			$this->output->writeln( 'Compiling in directory ' . ( $repoDir ?: $workingDir ) );
		}
		$make = proc_open(
			'make CFLAGS=' . escapeshellarg( self::CFLAGS ),
			[
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes,
			$repoDir ?: $workingDir,
			null,
			[
				'suppress_errors' => true,
				'bypass_shell' => true,
			]
		);

		// Extract all stdout and stderr
		$output = '';
		$error = '';
		while ( !feof( $pipes[1] ) ) {
			$output .= stream_get_contents( $pipes[1] );
		}
		while ( !feof( $pipes[2] ) ) {
			$error .= stream_get_contents( $pipes[2] );
		}

		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE ) {
			$this->output->write( $output );
		}
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
			$this->output->write( $error );
		}

		// Wait for termination
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $make );

		// Check for compile failure
		if ( $status !== 0 ) {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
				$this->output->writeln( 'Compilation Failed' );
			}

			$grade->setGrade( 0 );
			$grade->setGradeReason( 'Compilation Failed' );
		} else {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
				$this->output->writeln( 'Success' );
			}

			$grade->setGrade( null );
			$grade->setGradeReason( 'Awaiting Review' );
		}
		$grade->setGradeExtendedReason( "$error\n$output" );

		// Cleanup
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
			$this->output->writeln( "Removing working directory $workingDir" );
		}
		$fs->remove( $workingDir );
	}

	/**
	 * Download an assignment from BitBucket
	 *
	 * @param Grade $grade Grade to retrieve for
	 * @param string $commit Commit to download
	 */
	private function downloadFromBitbucket( Grade $grade, $commit ) {
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
			$url =
				"https://bitbucket.org/{$user->getUsername()}/{$user->getRepository()}/get/$commit.tar.gz";

			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
				$this->output->writeln( "Donwloading from $url" );
			}

			$client->get( $url, [ 'save_to' => $filename ] );
		} catch ( ClientException $e ) {
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG ) {
				$this->output->writeln( "Donwload error: {$e->getMessage()}" );
			}

			$grade->setGrade( null );
			$grade->setGradeReason( 'Download Error' );
			$grade->setGradeExtendedReason( $e->getMessage() );

			return;
		}

		$grade->setFile( new File( $filename ) );
	}
}
