<?php

namespace TeraBlaze\Profiler\Controllers\DebugBar;

use TeraBlaze\HttpBase\Response;

class AssetController extends BaseController
{
    /**
     * Return the javascript for the Debugbar
     *
     * @return Response
     */
    public function js()
    {
        $renderer = $this->debugbar->getJavascriptRenderer();

        $content = $renderer->dumpAssetsToString('js');

        $response = new Response(
            $content, 200, [
                'Content-Type' => 'text/javascript',
            ]
        );

        return $this->cacheResponse($response);
    }

    /**
     * Return the stylesheets for the Debugbar
     *
     * @return Response
     */
    public function css()
    {
        $renderer = $this->debugbar->getJavascriptRenderer();

        $content = $renderer->dumpAssetsToString('css');

        $response = new Response(
            $content, 200, [
                'Content-Type' => 'text/css',
            ]
        );

        return $this->cacheResponse($response);
    }

    /**
     * Return the fonts for the Debugbar
     *
     * @return Response
     */
    public function font(string $font)
    {
        $content = file_get_contents(kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/vendor/font-awesome/fonts/' . $font);

        $response = new Response(
            $content, 200, [
                'Content-Type' => 'text/css',
            ]
        );

        return $this->cacheResponse($response);
    }

    /**
     * Cache the response 1 year (31536000 sec)
     */
    protected function cacheResponse(Response $response)
    {
//        $response->setSharedMaxAge(31536000);
//        $response->setMaxAge(31536000);
//        $response->setExpires(new \DateTime('+1 year'));

        return $response;
    }
}
