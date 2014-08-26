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

namespace CS585Grader\SubmissionBundle\Form;


use CS585Grader\AccountBundle\Entity\User;
use CS585Grader\SubmissionBundle\Entity\Assignment;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Transformer that moves uploaded files into the proper directory
 *
 * @package CS585Grader\SubmissionBundle\Form
 */
class FileSubmissionModelTransformer implements DataTransformerInterface {
	/** @var string Base directory to install in */
	protected $baseDir;

	/** @var Assignment The assignment to store under */
	protected $assignment;

	/** @var User The user submitting the form */
	protected $user;

	/**
	 * @param Assignment $assignment
	 * @param User $user
	 * @param $baseDir
	 */
	public function __construct( Assignment $assignment, User $user, $baseDir ) {
		$this->assignment = $assignment;
		$this->user = $user;
		$this->baseDir = $baseDir;
	}

	/**
	 * Do not transform existing files
	 *
	 * @param File $value
	 *
	 * @return File
	 */
	public function transform( $value ) {
		return $value;
	}

	/**
	 * Move an uploaded file into the uploads directory
	 *
	 * @param UploadedFile $value
	 *
	 * @return File
	 * @throws \InvalidArgumentException
	 */
	public function reverseTransform( $value ) {
		// Handle empty values
		if ( !is_object( $value ) ) {
			return null;
		}

		if ( !$value instanceof UploadedFile ) {
			throw new \InvalidArgumentException( '$value must be an uploaded file.' );
		}

		return $value->move( $this->baseDir,
			"{$this->assignment->getName()}-{$this->user->getUsername()}.tar.gz" );
	}
}
