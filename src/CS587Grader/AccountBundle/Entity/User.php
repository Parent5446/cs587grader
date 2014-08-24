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


namespace CS587Grader\AccountBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Entity\User as BaseUser;
use GuzzleHttp\Subscriber\Oauth\Oauth1 as GuzzleOauth1;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Entity representing a Stevens student or faculty and associated
 * credentials
 *
 * @package CS587Grader\AccountBundle\Entity
 */
class User extends BaseUser {
	/** @var int Auto-generated surrogate key */
	protected $id;

	/** @var string OAuth access token */
	protected $accessToken;

	/** @var string OAuth access token secret */
	protected $accessTokenSecret;

	/** @var string OAuth refresh token */
	protected $refreshToken;

	/** @var string Repository the user will submit from */
	protected $repository;

	/** @var \CS587Grader\SubmissionBundle\Entity\Grade[]|ArrayCollection */
	protected $submissions;

	/**
	 * Initialize private properties
	 */
	public function __construct() {
		parent::__construct();

		$this->submissions = new ArrayCollection();
	}

	/**
	 * Set the OAuth tokens for the user
	 *
	 * @param string $token Access token
	 * @param string $secret Token secret
	 * @param string $refresh Refresh token [optional]
	 */
	public function setTokens( $token, $secret, $refresh = null ) {
		$this->accessToken = $token;
		$this->accessTokenSecret = $secret;
		$this->refreshToken = $refresh;
	}

	/**
	 * Get a Guzzle client set up for accessing this user's Bitbucket information
	 *
	 * @param string $consumerKey OAuth consumer key
	 * @param string $consumerSecret OAuth consumer secret
	 *
	 * @return \GuzzleHttp\Client
	 */
	public function getBitbucketClient( $consumerKey, $consumerSecret ) {
		$client = new GuzzleClient( [
			'base_url' => 'https://bitbucket.org/api/1.0/',
			'defaults' => ['auth' => 'oauth']
		] );

		$client->getEmitter()->attach( new GuzzleOauth1( [
			'consumer_key' => $consumerKey,
			'consumer_secret' => $consumerSecret,
			'token' => $this->accessToken,
			'token_secret' => $this->accessTokenSecret,
		] ) );

		return $client;
	}

	/**
	 * Set the repository from which the user will submit assignments
	 *
	 * @param string $name Full repo name (with owner)
	 */
	public function setRepository( $name ) {
		$this->repository = $name;
	}

	/**
	 * Get the name of the user's repository
	 *
	 * @return string Full repo name (with owner)
	 */
	public function getRepository() {
		return $this->repository;
	}
}
