<?php
/**
 * @since 2.1.2
 */
interface mailerExtendedTransportInterface extends Swift_Transport
{
    /**
     * Called once before sending a bunch of messages.
     * @param array $campaign mailer_campaign
     * @param bool $is_test true if sending test messages before actual campaign is started
     */
    public function setCampaign($campaign, $is_test=false);

    /**
     * Called after sending a bunch of test messages before campaign is started.
     * Returns HTML to show to user, explaining results of the test, if necessary.
     * @return string
     */
    public function testSendingReport();
}
