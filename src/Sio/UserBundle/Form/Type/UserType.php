<?php

namespace Sio\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Sio\SemiBundle\Controller\DefaultController as SemiDefaultController;
use Sio\SemiBundle\Entity\SeminarRepository;
/**
 * Description of RegistrationFormType
 *
 * @author kpu
 */
class UserType extends AbstractType {

    private $allStatusUserSeminar;
    private $idStatusUserSeminarChecked;
        
    public function __construct($allStatusUserSeminar, $idStatusUserSeminarChecked = null)
    {
      $this->allStatusUserSeminar = $allStatusUserSeminar;
      $this->idStatusUserSeminarChecked = $idStatusUserSeminarChecked;
    }
  
  public function buildForm(FormBuilderInterface $builder, array $options) {
    $builder->add('lastName');
    $builder->add('firstName');
    $builder->add('pass1', 'password', array('mapped' => false));
    $builder->add('pass2', 'password', array('mapped' => false));
    $builder->add('homeCity');
    $builder->add('organisation', 'entity', 
     array('class'=> 'SioSemiBundle:Organisation', 'property' => 'name' ));
     
    if ($this->allStatusUserSeminar) :     
      $builder->add('status', 'choice', array(
        'choices'   => $this->allStatusUserSeminar,
        'required'  => false,
        'mapped' => false,
        'expanded'=> TRUE,
        'multiple'=> FALSE,  
        'required' => TRUE,
        'data' => $this->idStatusUserSeminarChecked  
      ));
      
      //$seminar = $this->session->get(SemiDefaultController::SEMINAR);
      //$repoSeminar = $manager->getRepository('SioSemiBundle:Seminar');
      //$allStatusUserSeminar = $repoSeminar->getAllUserStatusBySeminar($seminar);
     /*
      $builder->add('status','entity',
          array(
          'mapped' => false,
          'class' => 'Sio\SemiBundle\Entity\Status',
          'property' => 'status',
          'query_builder' => function (\Sio\SemiBundle\Entity\SeminarRepository $repoSe) use ($seminar){
            $qb = $repoSe->getAllUserStatusBySeminar($seminar);
            return $qb;
          },
          'label' => 'status'
        ));
      */
    endif;
    $builder->add('Valider', 'submit');    
  }

  public function setDefaultOptions(OptionsResolverInterface $resolver) {
    $resolver->setDefaults(array(
        'data_class' => 'Sio\UserBundle\Entity\User',
        'cascade_validation' => true,
        'validation_groups' => array('registration'),
    ));
  }

  public function getName() {
    return 'sio_user';
  }

}