<?php

namespace TeraBlaze\Controller;

use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\View\Exception\Argument as ViewArgumentException;

/**
 * Class ErrorsController
 * @package TeraBlaze\Controller
 */
class ErrorController extends Controller
{
    public function renderErrorPage(Request $request, int $errorCode = 404): Response
    {
        $data['pageTitle'] = $errorCode . ' | ' . Response::PHRASES[$errorCode];
        $data['bodyMessage'] = $errorCode . ' | ' . Response::PHRASES[$errorCode];
        try {
            return $this->render('App::errors/' . $errorCode, $data);
        } catch (ViewArgumentException $vException) {
            return $this->render('App::errors/generic', $data);
        } catch (ViewArgumentException $vException) {
            return new Response(null, $errorCode);
        }
        return new Response(null, $errorCode);
    }
}
