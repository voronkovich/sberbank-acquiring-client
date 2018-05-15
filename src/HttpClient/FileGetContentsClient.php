<?php

namespace Voronkovich\SberbankAcquiring\HttpClient;

/**
 * Simple HTTP client using file_get_contents() with stream_context_create().
 *
 * @author     disjointed
 */
class FileGetContentsClient implements HttpClientInterface
{
	public function request($uri, $method = 'GET', array $headers = array(), array $data = array())
	{
		$response = file_get_contents($uri, false, stream_context_create([
			'http' => [
				'method' => $method,
				'header' => $headers,
				'content' => http_build_query($data),
				'host' => 'securepayments.sberbank.ru',
				'ignore_errors' => true,
			]
		]));

		$statusCode = 0;

		if (is_array($http_response_header)) {
			$statusCodeParts = explode(' ', $http_response_header[0]);
			
			if (count($statusCodeParts) > 1) {
	            $statusCode = intval($statusCodeParts[1]);
			}
		}

		return array($statusCode, $response);
	}
}
