<?php

namespace KnpU\CodeBattle\Controller;

use JMS\Serializer\SerializationContext;
use KnpU\CodeBattle\Api\ApiProblem;
use KnpU\CodeBattle\Api\ApiProblemException;
use KnpU\CodeBattle\Model\Programmer;
use KnpU\CodeBattle\Model\User;
use KnpU\CodeBattle\Repository\UserRepository;
use KnpU\CodeBattle\Application;
use Silex\Application as SilexApplication;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpFoundation\Request;
use KnpU\CodeBattle\Repository\ProgrammerRepository;
use KnpU\CodeBattle\Repository\ProjectRepository;
use KnpU\CodeBattle\Security\Token\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Base controller class to hide Silex-related implementation details
 */
abstract class BaseController implements ControllerProviderInterface
{
    /**
     * @var \KnpU\CodeBattle\Application
     */
    protected $container;

    public function __construct(Application $app)
    {
        $this->container = $app;
    }

    abstract protected function addRoutes(ControllerCollection $controllers);

    public function connect(SilexApplication $app)
    {
        $controllers = $app['controllers_factory'];

        $this->addRoutes($controllers);

        return $controllers;
    }

    /**
     * Render a twig template
     *
     * @param  string $template  The template filename
     * @param  array  $variables
     * @return string
     */
    public function render($template, array $variables = array())
    {
        return $this->container['twig']->render($template, $variables);
    }

    /**
     * Is the current user logged in?
     *
     * @return boolean
     */
    public function isUserLoggedIn()
    {
        return $this->container['security']->isGranted('IS_AUTHENTICATED_FULLY');
    }

    /**
     * @return User|null
     */
    public function getLoggedInUser()
    {
        if (!$this->isUserLoggedIn()) {
            return;
        }

        return $this->container['security']->getToken()->getUser();
    }

    /**
     * @param  string $routeName  The name of the route
     * @param  array  $parameters Route variables
     * @param  bool   $absolute
     * @return string A URL!
     */
    public function generateUrl($routeName, array $parameters = array(), $absolute = false)
    {
        return $this->container['url_generator']->generate(
            $routeName,
            $parameters,
            $absolute
        );
    }

    /**
     * @param  string           $url
     * @param  int              $status
     * @return RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Logs this user into the system
     *
     * @param User $user
     */
    public function loginUser(User $user)
    {
        $token = new UsernamePasswordToken($user, $user->getPassword(), 'main', $user->getRoles());

        $this->container['security']->setToken($token);
    }

    public function setFlash($message, $positiveNotice = true)
    {
        /** @var Request $request */
        $request = $this->container['request_stack']->getCurrentRequest();
        $noticeKey = $positiveNotice ? 'notice_happy' : 'notice_sad';

        $request->getSession()->getFlashbag()->add($noticeKey, $message);
    }

    /**
     * Used to find the fixtures user - I use it to cheat in the beginning
     *
     * @param $username
     * @return User
     */
    public function findUserByUsername($username)
    {
        return $this->getUserRepository()->findUserByUsername($username);
    }

    /**
     * Shortcut for saving objects
     *
     * @param $obj
     */
    public function save($obj)
    {
        switch (true) {
            case ($obj instanceof Programmer):
                $this->getProgrammerRepository()->save($obj);
                break;
            default:
                throw new \Exception(sprintf('Shortcut for saving "%s" not implemented', get_class($obj)));
        }
    }

    /**
     * Shortcut for deleting objects
     *
     * @param $obj
     */
    public function delete($obj)
    {
        switch (true) {
            case ($obj instanceof Programmer):
                $this->getProgrammerRepository()->delete($obj);
                break;
            default:
                throw new \Exception(sprintf('Shortcut for saving "%s" not implemented', get_class($obj)));
        }
    }

    public function throw404($message = 'Page not found')
    {
        throw new NotFoundHttpException($message);
    }

    /**
     * @param $obj
     * @return array
     */
    public function validate($obj)
    {
        return $this->container['api.validator']->validate($obj);
    }

    /**
     * @return UserRepository
     */
    protected function getUserRepository()
    {
        return $this->container['repository.user'];
    }

    /**
     * @return ProgrammerRepository
     */
    protected function getProgrammerRepository()
    {
        return $this->container['repository.programmer'];
    }

    /**
     * @return ProjectRepository
     */
    protected function getProjectRepository()
    {
        return $this->container['repository.project'];
    }

    /**
     * @return \KnpU\CodeBattle\Repository\BattleRepository
     */
    protected function getBattleRepository()
    {
        return $this->container['repository.battle'];
    }

    /**
     * @return \KnpU\CodeBattle\Battle\BattleManager
     */
    protected function getBattleManager()
    {
        return $this->container['battle.battle_manager'];
    }

    /**
     * @return ApiTokenRepository
     */
    protected function getApiTokenRepository()
    {
        return $this->container['repository.api_token'];
    }
    protected function serialize( $data)
    {
        $serializerContext = new SerializationContext();
        $serializerContext->setSerializeNull(true);
        return $this->container['serializer']
            ->serialize($data, 'json', $serializerContext);
    }

    protected function createApiResponse($data, $statusCode = 200)
    {
        $json = $this->serialize($data);

        $response = new Response($json, $statusCode, array(
            'Content-Type' => 'application/json'
        ));
        return $response;
    }
    protected function enforceUserSecurity()
    {
        if (! $this->getLoggedInUser()){
            throw new AccessDeniedException();
        }
    }
    protected function enforceProgrammerOwnershipSecurity(Programmer $programmer)
    {
        $this->enforceUserSecurity();

        if ($programmer->userId!= $this->getLoggedInUser()->id){
            throw new AccessDeniedException();
        }
    }
    protected function decodeRequestBodyIntoParameters(Request $request)
    {
        if (! $request->getContent()){
            $data = array();
        }else{
            $data= json_decode($request->getContent(), true);
            if ($data === null) {
                $problem = new ApiProblem(
                    400,
                    ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT
                );
                throw new ApiProblemException($problem);
            }
        }
        return new ParameterBag($data) ;
    }


}
