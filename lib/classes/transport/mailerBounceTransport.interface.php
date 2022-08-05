<?php
/**
 * @since 2.1.2
 */
interface mailerBounceTransportInterface
{
    /**
     * Occasionally called by Mailer in order to collect bounced emails collected by this transport.
     *
     * Bounce info is
     * - either a string describing what happened in human-readable form. Mailer will do its best to classify the error based on this text.
     * - or an array with detailed info [
     *    'error_text' => string: human-readable error description
     *    'error_type' => string: error class to classify this bounce under
     *    'is_fatal' => boolean: whether this address should be marked as unavailable for further campaigns
     * ]
     *
     * For more information and examples of error types, see comments for bounce_types in mailer/lib/config/config.php
     *
     * @param int $limit
     * @return array x-log-id => bounce info
     */
    public function getBounces($limit);

    /**
     * This method is called (via register_shutdown_function) when bounce check is interrupted by max exec time.
     */
    public function bounceCheckerShutdown();
}
