<?php


namespace CS585Grader\SubmissionBundle\Form\Type;


use CS585Grader\SubmissionBundle\Form\FileSubmissionModelTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Custom type that represents a file upload and moves it to the correct directory
 *
 * @package CS585Grader\SubmissionBundle\Form\Type
 */
class FileSubmissionType extends AbstractType {
	/** @var string Base upload directory */
	protected $baseDir;

	/**
	 * @param string $baseDir Base upload directory
	 */
	public function __construct( $baseDir ) {
		$this->baseDir = $baseDir;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'cs587_submission';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParent() {
		return 'file';
	}

	/**
	 * {@inheritdoc}
	 */
	public function setDefaultOptions( OptionsResolverInterface $resolver ) {
		$resolver->setRequired( [ 'assignment', 'user' ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$builder->addModelTransformer( new FileSubmissionModelTransformer(
				$options['assignment'],
				$options['user'],
				$this->baseDir
			) );
	}
}
