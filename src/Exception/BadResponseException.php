<?php

namespace Voronkovich\SberbankAcquiring\Exception;

/**
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class BadResponseException extends SberbankAcquiringException
{
    private $response;

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}
