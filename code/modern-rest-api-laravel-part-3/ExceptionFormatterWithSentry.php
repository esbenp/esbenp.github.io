<?php

namespace Infrastructure\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\ExceptionFormatter as BaseExceptionFormatter;

class ExceptionFormatter extends BaseExceptionFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        parent::format($response, $e, $reporterResponses);

        $response->setData(array_merge(
            (array) $response->getData(),
            ['sentry_id' => $reporterResponses['sentry']]
        ));

        return $response;
    }
}
