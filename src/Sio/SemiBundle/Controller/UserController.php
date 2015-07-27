<?php

namespace Sio\SemiBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sio\SemiBundle\Controller\DefaultController as SemiDefaultController;
use Sio\UserBundle\Entity\User as User;
use Sio\UserBundle\Form\Type\UserType as UserType;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Sio\SemiBundle\Entity\UserSeminar as UserSeminar;
use FOS\UserBundle\Model\UserInterface;

/**
 * @Route("/user")
 */
class UserController extends Controller {

  const ROUTE_LOGIN = 'fos_user_security_login';
  
   /**
   *  
   * @Route("/user/register", name="_semi_user_register")
   * @Template()
   */
  public function registerAction(Request $request) {
    $session = $request->getSession();
    $u = $this->getUser();
    if (is_object($u) && $u instanceof UserInterface && $u.getId()) :
      return $this->updateProfilAction($request);   
    endif;  
    
    if (!$session->has(SemiDefaultController::EMAIL_FOR_REGISTER)
        || !$session->has(DefaultController::SEMINAR_KEY)) :
      throw new AccessDeniedException('Semi : register (1)');
    endif;
    
    $seminarKey = $session->get(DefaultController::SEMINAR_KEY);
    
    $manager = $this->getDoctrine()->getManager();
    $repoSeminar = $manager->getRepository('SioSemiBundle:Seminar');
    $seminar = $repoSeminar->findOneByClef($seminarKey);
    
    if (!$seminar) :
      // one is never too careful ...
      throw new AccessDeniedException('Semi : register (2)');
    endif;
    
    $user = new User();
    $allStatusUserSeminar = $repoSeminar->getAllUserStatusBySeminar($seminar);
 
    $form = $this->createForm(new UserType($allStatusUserSeminar), $user);
    $form->handleRequest($request);

    if ($form->isValid() && $this->validationExtra($form)) {
           
      $user->setEmail($session->get(SemiDefaultController::EMAIL_FOR_REGISTER));
      $factory = $this->get('security.encoder_factory');
      $encoder = $factory->getEncoder($user);
      $password = $encoder->encodePassword($form->get('pass1')->getData(), null);
      // whith bcrypt salt will be generate and integrate into password
      // http://stackoverflow.com/questions/25760520/does-symfony-derive-the-salt-from-the-hash-or-isnt-the-hash-salted-at-all
      $user->setPassword($password);
      
      // TODO : setRole ne marche pas...
      $user->setRoles(array("ROLE_USER")); 
      // FOSUser say not null (and unique ? so we put mail)
      $user->setUsername($user->getEmail());                
      $user->setEnabled(true); 
      $userIdStatusForThisSeminar = $form->get('status')->getData();      
      $em = $this->getDoctrine()->getManager();
      // suspend auto-commit
      $em->getConnection()->beginTransaction();
      try {
       $em->persist($user);
       $em->flush(); // for get id 
       $this->registerUserSeminar($em, $seminar, $user, $userIdStatusForThisSeminar);
       $em->flush(); 
       // Try and commit the transaction
       $em->getConnection()->commit();
       // it is no longer useful in session
       $session->remove(SemiDefaultController::EMAIL_FOR_REGISTER);    
       $this->get('session')
          ->getFlashBag()
          ->add('success', 'Votre compte a bien été créé (' 
              . $user->getFirstName() . ')');
      } catch (Exception $ex) {
        // Rollback the failed transaction attempt
        $em->getConnection()->rollback();
        throw $ex;
      }
      // auto connect  
      $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
      $this->get('security.context')->setToken($token);
      return $this->redirect($this->generateUrl('_semi_default_redirect'));
    }
  
    return array(
        'form' => $form->createView(), 
        'user' => $user,
        'userEmail' => $session->get(SemiDefaultController::EMAIL_FOR_REGISTER),
     //   'menuItemActive' => 'profil'
    );
  }
  
  /**
   * Validate password and userIdStatusForThisSeminar...
   * @param $form
   */
  private function validationExtra($form) {
    
    $pass1 = $form->get('pass1')->getData();
    $pass2 = $form->get('pass2')->getData();
    $okpw = ($pass1 && ($pass1==$pass2));
    if (!$okpw) :
      $this->get('session')
          ->getFlashBag()
          ->add('notice', 'Les mots de passe ne correspondent pas !');
    endif;
    
    $userIdStatusForThisSeminar = $form->get('status')->getData();
    $okStatus = TRUE == ($this->getDoctrine()->getManager()
        ->getRepository('SioSemiBundle:Status')
        ->find($userIdStatusForThisSeminar));
    
    return $okpw && $okStatus;  
  }
  
  
  /**
   * Validate password if not 'nochange'...
   * @param $form
   */
  private function validationExtraUpdateProfil($form) {
    
    $pass1 = $form->get('pass1')->getData();
    $pass2 = $form->get('pass2')->getData();
    if ($pass1 != 'nochange') :
      $okpw = ($pass1 && ($pass1==$pass2));
    else:
      $okpw = $pass1==$pass2; // no change password 
    endif;
    
    if (!$okpw) :
      $this->get('session')
          ->getFlashBag()
          ->add('notice', 'Les mots de passe ne correspondent pas !');
    endif;
    $okStatus = TRUE;
    if ($form->has('status')):
      $userIdStatusForThisSeminar = $form->get('status')->getData();
      $okStatus = TRUE == ($this->getDoctrine()->getManager()
        ->getRepository('SioSemiBundle:Status')
        ->find($userIdStatusForThisSeminar));  
    endif;
    
    
    return $okpw && $okStatus;  
  }
  
  
  /**
   * Persist a new UserSeminar Status
   * @param EntityManager $em (for transation by caller)
   * @param Seminar $seminar
   * @param User $user
   * @param String $idStatus
   */
  function registerUserSeminar($em, $seminar, $user, $idStatus ) {    
    $userStatus = //$this->getDoctrine()
        $em->getRepository('SioSemiBundle:Status')
        ->find($idStatus);

    $statusUserSeminar = //$this->getDoctrine()
      $em->getRepository('SioSemiBundle:UserSeminar')
        ->findOneBy(array('seminar' => $seminar
        , 'user'=> $user));  
    
    if ($statusUserSeminar) {
      $statusUserSeminar->setStatus($userStatus);
      $em->persist($statusUserSeminar);
      $em->flush(); 
       $this->get('session')->getFlashBag()->add('success', 'Satus Update ' . $statusUserSeminar);
    } else {
      $newUserSeminar = new UserSeminar();
      $newUserSeminar->setSeminar($seminar);
      $newUserSeminar->setStatus($userStatus);
      $newUserSeminar->setUser($user);
      $em->persist($newUserSeminar);
      $em->flush(); 
      // $this->get('session')->getFlashBag()->add('success', 'Satus Create');
    }
  }

   /** 
   * @Route("/user/profil", name="_semi_user_profil")
   */
  public function updateProfilAction(Request $request) {
    $session = $request->getSession();
    $seminar = NULL;
    $allStatusUserSeminar = NULL;
    $idStatus = NULL;
    $user = $this->getUser();
    if (!is_object($user) || !$user instanceof UserInterface) :
      throw new AccessDeniedException('Semi : update profil (1)');
    endif;
    
    $seminarId = $session->get(DefaultController::SEMINAR_ID);
    if ($seminarId) :
      $manager = $this->getDoctrine()->getManager();
      $repoSeminar = $manager->getRepository('SioSemiBundle:Seminar');
      $seminar = $repoSeminar->find($seminarId);    
      if ($seminar) :
        $allStatusUserSeminar = $repoSeminar->getAllUserStatusBySeminar($seminar);
        $idStatus = $this->getIdStatusByUser($user, $seminar);
      endif;
    endif;

    $form = $this->createForm(new UserType($allStatusUserSeminar, $idStatus), $user);
    $form->handleRequest($request);

    if ($form->isValid() && $this->validationExtraUpdateProfil($form)) {
      if ($form->get('pass1')->getData() != 'nochange') :        
        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        $password = $encoder->encodePassword($form->get('pass1')->getData(), null);
        // whith bcrypt salt will be generate and integrate into password
        // http://stackoverflow.com/questions/25760520/does-symfony-derive-the-salt-from-the-hash-or-isnt-the-hash-salted-at-all
        $user->setPassword($password);
      endif;        
      // user can direct connect (without choice a seminar)
      $userIdStatusForThisSeminar = NULL;
      if ($form->has('status')) :
         $userIdStatusForThisSeminar = $form->get('status')->getData();     
      endif;
      $em = $this->getDoctrine()->getManager();
      // suspend auto-commit
      $em->getConnection()->beginTransaction();
      try {
       $em->persist($user);
       $em->flush(); // for get id 
       if ($userIdStatusForThisSeminar):
         $this->registerUserSeminar($em, $seminar, $user, $userIdStatusForThisSeminar);         
       endif;
       // Try and commit the transaction
       $em->getConnection()->commit();       
       $this->get('session')
          ->getFlashBag()
          ->add('success', 'Votre compte a bien été modifié (' 
              . $user->getFirstName() . ')');
      } catch (Exception $ex) {
        // Rollback the failed transaction attempt
        $em->getConnection()->rollback();
        throw $ex;
      }

      return $this->redirect($this->generateUrl('_semi_default_redirect'));
    }
  
    $toview = array(
        'form' => $form->createView(), 
        'user' => $user,
        'userEmail' => $user->getEmail(),
    );
    return $this
            ->render('SioSemiBundle:User:register.html.twig', $toview);
  }  
  
  /**
   * Determine if date now inner [$dateStart..$dateEnd]
   * @param \DateTime $dateStart
   * @param \DateTime $dateEnd
   * @return boolean
   */
  function allowRegistration($dateStart, $dateEnd) {
    $now = new \DateTime();
    return $dateStart <= $now && $now <= $dateEnd;
  }

  /**
   * 
   * @param User $user
   * @param Seminar $seminar
   * @return id of user status for this seminar or null
   */
  function getIdStatusByUser($user, $seminar) {
    $manager = $this->getDoctrine()->getManager();
    $status = $manager->getRepository('SioSemiBundle:UserSeminar')
            ->findOneBy(array('seminar' => $seminar, 'user'=> $user));
    if ($status) :
      $idStatus = $status->getStatus()->getId();
    else :
      $idStatus = null; 
    endif;
   
    return $idStatus;
  }
  
}