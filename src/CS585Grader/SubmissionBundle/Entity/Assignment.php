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

namespace CS585Grader\SubmissionBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Entity representing an assignment (creatable by admins)
 *
 * @package CS585Grader\SubmissionBundle\Entity
 */
class Assignment {
	/** @var string Name of the assignment (<32 chars) */
	protected $name;

	/** @var string Short description (<255 chars)  */
	protected $description;

	/** @var \DateTime Date and time the assignment will be graded */
	protected $dueDate;

	/** @var \CS585Grader\SubmissionBundle\Entity\Grade[]|ArrayCollection */
	protected $submissions;

	/**
	 * Make a new assignment
	 *
	 * @param string $name Name for the assignment
	 */
	public function __construct( $name ) {
		$this->name = $name;
		$this->submissions = new ArrayCollection();
		$this->dueDate = new \DateTime();
	}

	/**
	 * Get the name of the assignment
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Change the name of the assignment
	 *
	 * @param string $name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * Get the description of the assignment
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set the description of the assignment
	 *
	 * @param string $description
	 */
	public function setDescription( $description ) {
		$this->description = $description;
	}

	/**
	 * Get the due date
	 *
	 * @return \DateTime
	 */
	public function getDueDate() {
		return $this->dueDate;
	}

	/**
	 * Set the due date
	 *
	 * @param \DateTime $dueDate
	 */
	public function setDueDate( \DateTime $dueDate ) {
		$this->dueDate = $dueDate;
	}

	/**
	 * Get the list of submissions
	 *
	 * @return Grade[]|ArrayCollection
	 */
	public function getSubmissions() {
		return $this->submissions;
	}
}
