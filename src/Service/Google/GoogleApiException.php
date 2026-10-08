<?php

declare(strict_types=1);

namespace App\Service\Google;

/**
 * Google (OAuth or Calendar API) refused a request or could not be reached. The message is meant for the admin.
 */
class GoogleApiException extends \RuntimeException
{
}
