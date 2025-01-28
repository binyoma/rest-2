<?php

namespace KnpU\CodeBattle\Controller\Api;

use KnpU\CodeBattle\Controller\BaseController;
use KnpU\CodeBattle\Security\Token\ApiToken;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;

class TokenController extends BaseController
{


    protected function addRoutes(ControllerCollection $controllers)
    {
        $controllers->post('/api/tokens', array($this, 'newAction'));
    }
    public function newAction(Request $request)
    {
        $this->enforceUserSecurity();
        $data = $this->decodeRequestBodyIntoParameters($request);
        $token = new ApiToken($this->getLoggedInUser()->id);
        $token->notes=$data->get('notes','defaut note')  ;

        $this->getApiTokenRepository()->save($token);

       return $this->createApiResponse($token, 201);
    }
}