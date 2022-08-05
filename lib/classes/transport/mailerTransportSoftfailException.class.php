<?php
/**
 * Transport may throw this during send() in order to request user attention.
 * This will pause sending of current campaign.
 */
class mailerTransportSoftfailException extends waException
{
}
