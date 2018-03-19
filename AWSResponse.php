<?php

namespace UltimateGuitar\SwiftSesTransport;

use UltimateGuitar\SwiftSesTransport\Exceptions\AWSEmptyResponseException;
use UltimateGuitar\SwiftSesTransport\Exceptions\InvalidHeaderException;

class AWSResponse
{

    public $headers = array();
    public $code = 0;
    public $message = '';
    public $body = '';
    public $xml = null;

    const STATE_EMPTY = 0;
    const STATE_HEADERS = 1;
    const STATE_BODY = 2;

    protected $state = self::STATE_EMPTY;

    public function line ( $line )
    {
        switch( $this->state )
        {
            case self::STATE_EMPTY:
                if( ! $line )
                {
                    throw new AWSEmptyResponseException();
                }
                $split = explode( ' ', $line );
                $this->code = $split[1];
                $this->message = implode( array_slice( $split, 2 ), ' ' );
                $this->state = self::STATE_HEADERS;
                break;
            case self::STATE_HEADERS:
                if( "\r\n" == $line )
                {
                    $this->state = self::STATE_BODY;
                    break;
                }

                $pos = strpos( $line, ':' );
                if( false === $pos )
                {
                    throw new InvalidHeaderException( $line );
                }
                $key = substr( $line, 0, $pos );
                $this->headers[$key] = substr( $line, $pos );
                break;
            case self::STATE_BODY:
                $this->body .= $line;
                break;
        }

    }

    public function complete ()
    {
        $this->xml = simplexml_load_string( $this->body );
    }

}