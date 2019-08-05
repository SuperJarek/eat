<?php

/**
 * uwierzytelnianie
 * tworzenie tokenu  na podstawie loginu oraz hasła klienta
 */
namespace App\Security\Jwt;

use App\Entity\Customers;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

use App\Services\Response\ResponseCreatorInterface;
use App\Services\Customers\CustomersInterface;

class JwtAuthenticator extends AbstractGuardAuthenticator {

  private const PASSWORD = '_password';
  private const USERNAME = '_login';


  private $responseFactory;
  /**
   * rdis client for storage token
   * @var TokenStorageInterface
   */
  private $tokenStorage;

  private $jwt;

  protected $encoderFactory;


  public function __construct(EncoderFactoryInterface $encoderFactory , ResponseCreatorInterface $responseFactory, TokenStorageInterface $ts, JwtInterface $jwt)
  {
      $this -> responseFactory = $responseFactory;
      $this -> tokenStorage = $ts;
      $this -> jwt = $jwt;
      $this -> encoderFactory = $encoderFactory;
  }

  /**
   * sprawdzam czy wykonac autentykacje
   * @param  Request $request [description]
   * @return [type]           [description]
   */
  public function supports(Request $request)
  {
      $pasword = $request-> request -> has(self::PASSWORD);
      $login = $request-> request -> has(self::USERNAME);

      return $pasword && $login;
  }

  /**
   * pobieram dane do uwierzyteleniania
   * @param  Request $request [description]
   * @return [type]           [description]
   */
  public function getCredentials(Request $request)
  {
    return [
      'password' => $request -> request -> get(self::PASSWORD),
      'login' => $request -> request -> get(self::USERNAME)
    ];
  }

  /**
   * zwracam uzytkownika
   * @param  [type]                $credentials  [description]
   * @param  UserProviderInterface $userProvider [description]
   * @return [type]                              [description]
   */
  public function getUser($credentials, UserProviderInterface $userProvider)
  {

    return $userProvider -> loadUserByUsername($credentials['login']);
  }


  public function checkCredentials($credentials, UserInterface $user)
  {

    #sprawdzam czy uzytkownik implementuje odpowiedni interface
    if(false == ($user instanceof CustomersInterface)) {

      throw new \Exception(sprintf(
        'user is not instanceof %s',
        CustomersInterface::class
      ));

    }

    $encoder = $this -> encoderFactory -> getEncoder($user);

     return $encoder->isPasswordValid(
         $user->getPassword(),
         $credentials['password'],
         $user->getSalt()
     );
  }


  /**
   * sukcej autentykacji
   * @param  Request        $request     [description]
   * @param  TokenInterface $token       [description]
   * @param  [type]         $providerKey [description]
   * @return [type]                      [description]
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
  {

    # tworze testowy token
    $user = $token -> getUser();
    $tokenKey = $this -> jwt -> encode($user);

    $this -> tokenStorage -> set(
      $tokenKey,
      $user -> getUsername()
    );

    $data = [
      'token' => $tokenKey
    ];

    return $this -> responseFactory -> createResponse($data, Response::HTTP_OK);
  }

  /**
   * metoda wykonywana gdy autentykacja napotka blad
   * @param  Request                 $request   [description]
   * @param  AuthenticationException $exception [description]
   * @return [type]                             [description]
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
  {
    $data = [
      'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
    ];

    return $this -> responseFactory -> createResponse($data,
     Response::HTTP_FORBIDDEN);
  }

  /**
   * metoda wykonywana gdy wymagana jest autoryzacja
   * @param  Request $request       [description]
   * @param  [type]  $authException [description]
   * @return [type]                 [description]
   */
  public function start(Request $request, AuthenticationException $authException = null)
  {

    $data = [
        'message' => 'Authentication Required'
    ];

    return $this -> responseFactory -> createResponse($data, Response::HTTP_UNAUTHORIZED);
  }

  public function supportsRememberMe()
  {
    return false;
  }

}
