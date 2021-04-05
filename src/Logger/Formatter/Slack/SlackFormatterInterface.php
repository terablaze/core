<?php

declare(strict_types=1);

namespace TeraBlaze\Logger\Formatter\Slack;

use Monolog\Formatter\FormatterInterface;

/**
 * This is an Interface that all formatters must extend in order to be passed into SlackWebhookHandler
 */
interface SlackFormatterInterface extends FormatterInterface
{
}
