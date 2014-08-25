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

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Exception\AccountNotLinkedException;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Custom user provider that auto-creates users if they do not exist
 *
 * @package CS587Grader\AccountBundle\Entity
 */
class UserProvider extends FOSUBUserProvider {
	/**
	 * {@inheritdoc}
	 */
	public function loadUserByOAuthUserResponse( UserResponseInterface $response ) {
		/** @var User $user */
		$user = null;

		try {
			$user = parent::loadUserByOAuthUserResponse( $response );
		} catch ( AccountNotLinkedException $e ) {
			$user = $this->userManager->createUser();
			$user->setUsername( $response->getUsername() );
			$user->addRole( 'ROLE_OAUTH_USER' );
		}

		$user->setTokens(
			$response->getAccessToken(),
			$response->getTokenSecret(),
			$response->getRefreshToken()
		);

		return $user;
	}

	/**
	 * @inheritdoc
	 * @param UserInterface|User $user
	 * @param UserResponseInterface $response
	 */
	public function connect( UserInterface $user, UserResponseInterface $response ) {
		if ( !$user instanceof User ) {
			throw new \LogicException( 'Non-local user found its way into database.' );
		}

		$user->setTokens(
			$response->getAccessToken(),
			$response->getTokenSecret(),
			$response->getRefreshToken()
		);

		parent::connect( $user, $response );
	}
}
