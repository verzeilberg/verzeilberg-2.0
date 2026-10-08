<?php

declare(strict_types=1);

namespace App\Service\Steam;

/**
 * A Steam request failed; the message is safe to show (it never contains the API key).
 */
class SteamApiException extends \RuntimeException
{
}
