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

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Kernel for the grading application
 */
class AppKernel extends Kernel
{
	/**
	 * Loads all the dependencies and all the bundles inside the application
	 *
	 * @return \Symfony\Component\HttpKernel\Bundle\BundleInterface[]
	 */
	public function registerBundles() {
		$bundles = [
			new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
			new Symfony\Bundle\SecurityBundle\SecurityBundle(),
			new Symfony\Bundle\TwigBundle\TwigBundle(),
			new Symfony\Bundle\MonologBundle\MonologBundle(),
			new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
			new Symfony\Bundle\AsseticBundle\AsseticBundle(),
			new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
			new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
			new JMS\DiExtraBundle\JMSDiExtraBundle( $this ),
			new JMS\AopBundle\JMSAopBundle(),
			new JMS\JobQueueBundle\JMSJobQueueBundle(),
			new \Nelmio\SecurityBundle\NelmioSecurityBundle(),
			new FOS\UserBundle\FOSUserBundle(),
			new HWI\Bundle\OAuthBundle\HWIOAuthBundle(),
			new CS585Grader\AccountBundle\CS585GraderAccountBundle(),
			new CS585Grader\SubmissionBundle\CS585GraderSubmissionBundle(),
		];

		if ( in_array( $this->getEnvironment(), [ 'dev', 'test' ] ) ) {
			$bundles[ ] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
			$bundles[ ] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
			$bundles[ ] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
		}

		return $bundles;
	}

	/**
	 * Register the configuration
	 *
	 * @param LoaderInterface $loader
	 */
	public function registerContainerConfiguration( LoaderInterface $loader ) {
		$loader->load( __DIR__ . '/Resources/config/config_' . $this->getEnvironment() . '.xml' );
	}
}
