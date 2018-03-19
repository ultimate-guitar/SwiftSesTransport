<?php

namespace UltimateGuitar\SwiftSesTransport;

use Swift_Transport;
use Swift_Mime_SimpleMessage;
use Swift_Events_EventListener;
use Swift_DependencyContainer;
use UltimateGuitar\SwiftSesTransport\Exceptions\AWSConnectionException;
use Swift_Events_SendEvent;

class SesTransport implements Swift_Transport
{
    public const DEFAULT_ENDPOINT = 'https://email.us-east-1.amazonaws.com/';
    private $event_dispatcher;

    /** the service access key */
    public $key_id;
    /** the service secret key */
    public $secret_key;
    /** the service endpoint */
    public $endpoint;
    /** the response */
    public $response;

    public function __construct($key_id = null , $secret_key = null, $endpoint = self::DEFAULT_ENDPOINT) {
        Swift_DependencyContainer::getInstance()
            ->register('transport.aws')
            ->withDependencies(array('transport.eventdispatcher'));
        call_user_func_array(
            array($this, 'setEventDispatcher'),
            Swift_DependencyContainer::getInstance()->createDependenciesFor('transport.aws')
        );
        $this->key_id = $key_id;
        $this->secret_key = $secret_key;
        $this->endpoint = $endpoint;
    }
    private function setEventDispatcher(\Swift_Events_EventDispatcher $dispatcher)
    {
        $this->event_dispatcher = $dispatcher;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        if ($evt = $this->event_dispatcher->createSendEvent($this, $message))
        {
            $this->event_dispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled())
            {
                return 0;
            }
        }

        $this->response = $this->proceed($message, $failedRecipients);

        $success = (200 == $this->response->code);
        $resp_event = $this->event_dispatcher->createResponseEvent($this, new SwiftResponse($message, $this->response->xml), $success);
        if ($resp_event)
        {
            $this->event_dispatcher->dispatchEvent($resp_event, 'responseReceived');
        }

        if ($evt)
        {
            $evt->setResult($success ? Swift_Events_SendEvent::RESULT_SUCCESS : Swift_Events_SendEvent::RESULT_FAILED);
            $this->event_dispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        if ($success)
        {
            return count((array)$message->getTo());
        }
        else
        {
            return 0;
        }
    }
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->event_dispatcher->bindEventListener($plugin);
    }

    private function proceed( Swift_Mime_SimpleMessage $message, &$failedRecipients = null )
    {
        $date = date( 'D, j F Y H:i:s O' );
        $hmac = base64_encode( hash_hmac( 'sha1', $date, $this->secret_key, true ) );
        $auth = "AWS3-HTTPS AWSAccessKeyId=" . $this->key_id . ", Algorithm=HmacSHA1, Signature=" . $hmac;

        $host = parse_url( $this->endpoint, PHP_URL_HOST );
        $path = parse_url( $this->endpoint, PHP_URL_PATH );

        $fp = fsockopen( 'ssl://' . $host , 443, $errno, $errstr, 30 );

        if( ! $fp ) {
            throw new AWSConnectionException( "$errstr ($errno)" );
        }

        $socket = new Socket( $fp, $host, $path );

        $socket->header("Date", $date);
        $socket->header("X-Amzn-Authorization", $auth);

        $socket->write("Action=SendRawEmail&RawMessage.Data=");

        $ais = new AWSInputByteStream($socket);
        $message->toByteStream($ais);
        $ais->flushBuffers();

        $result = $socket->read();

        return $result;
    }

    public function isStarted()
    {
    }
    public function start()
    {
    }
    public function stop()
    {
    }
    public function ping()
    {
    }
}