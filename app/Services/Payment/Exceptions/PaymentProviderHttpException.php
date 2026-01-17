<?php

namespace App\Services\Payment\Exceptions;

use Illuminate\Http\Client\Response;

class PaymentProviderHttpException extends PaymentProviderException
{
    public function __construct(
        public readonly Response $response,
        string $message = 'Payment provider HTTP error.'
    ) {
        parent::__construct($message, $response->status());
    }
}
