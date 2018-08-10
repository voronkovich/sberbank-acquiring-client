<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\Exception;

/**
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class BadResponseException extends SberbankAcquiringException
{
    /**
     * @var string
     */
    private $response;

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }
}
