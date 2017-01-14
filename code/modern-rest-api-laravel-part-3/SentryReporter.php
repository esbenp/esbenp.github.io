<?php

namespace Optimus\Heimdal\Reporters;

use Exception;
use InvalidArgumentException;
use Raven_Client;
use Optimus\Heimdal\Reporters\ReporterInterface;

class SentryReporter implements ReporterInterface
{
    public function __construct(array $config)
    {
        if (!class_exists(Raven_Client::class)) {
            throw new InvalidArgumentException("Sentry client is not installed. Use composer require sentry/sentry.");
        }

        $this->raven = new Raven_Client($config['dsn'], $config['sentry_options']);
    }

    public function report(Exception $e)
    {
        return $this->raven->captureException($e);
    }
}
