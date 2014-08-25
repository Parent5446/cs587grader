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

namespace CS587Grader\SubmissionBundle\Entity;

use CS587Grader\AccountBundle\Entity\User;

/**
 * Represents a student's grade on a given assignment
 *
 * @package CS587Grader\SubmissionBundle\Entity
 */
class Grade {
	/** @var Assignment The assignment this grade is for */
	protected $assignment;

	/** @var \CS587Grader\AccountBundle\Entity\User Student who has the grade */
	protected $user;

	/** @var int|null The actual grade */
	protected $grade;

	/** @var string|null File key/name */
	protected $fileKey;

	/** @var string|null Reason for the grade (message key) */
	protected $reason;

	/** @var string|null Extended reason */
	protected $extendedReason;

	/**
	 * Make a new grade
	 *
	 * @param Assignment $assignment Assignment the grade is for
	 * @param User $user User to assign to
	 * @param int|null $grade Grade, or null for ungraded
	 * @param string|null $reason Reason for the grade (message key)
	 */
	public function __construct( Assignment $assignment, User $user, $grade, $reason = null ) {
		$this->assignment = $assignment;
		$this->user = $user;
		$this->grade = $grade;
		$this->reason = $reason;
	}

	/**
	 * Get the user the grade belongs to
	 *
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Get the assignment this grade is on
	 *
	 * @return Assignment
	 */
	public function getAssignment() {
		return $this->assignment;
	}

	/**
	 * Get the grade
	 *
	 * @return int|null
	 */
	public function getGrade() {
		return $this->grade;
	}

	/**
	 * Set the grade for the assignment
	 *
	 * @param int|null $grade
	 */
	public function setGrade( $grade ) {
		$this->grade = $grade;
	}

	/**
	 * Get the brief grade reasons
	 *
	 * @return null|string
	 */
	public function getGradeReason() {
		return $this->reason;
	}

	/**
	 * Set the short reason for the grade
	 *
	 * @param string $reason
	 */
	public function setGradeReason( $reason ) {
		$this->reason = $reason;
	}

	/**
	 * Get the extended reason for the grade
	 *
	 * @return null|string
	 */
	public function getGradeExtendedReason() {
		if ( is_resource( $this->extendedReason ) ) {
			return stream_get_contents( $this->extendedReason );
		} else {
			return $this->extendedReason;
		}
	}

	/**
	 * Set the extended reason for the grade
	 *
	 * @param string|null $reason
	 */
	public function setGradeExtendedReason( $reason ) {
		$this->extendedReason = $reason;
	}

	/**
	 * Set the file key where the submission is stored
	 *
	 * @param string $fileKey
	 *
	 * @return $this
	 */
	public function setFileKey( $fileKey ) {
		$this->fileKey = $fileKey;

		return $this;
	}

	/**
	 * Get the file key, if it exists
	 *
	 * @return null|string
	 */
	public function getFileKey() {
		return $this->fileKey;
	}
}
