<?php

namespace UltimateGuitar\SwiftSesTransport;


use UltimateGuitar\SwiftSesTransport\Exceptions\SocketInvalidOperationException;

class Socket
{
    public function __construct( $socket, $host, $path, $method="POST" )
    {

        $this->socket = $socket;
        $this->write_started = false;
        $this->write_finished = false;
        $this->read_started = false;

        fwrite( $this->socket, "$method $path HTTP/1.1\r\n" );

        $this->header( "Host", $host );
        if( "POST" == $method ) {
            $this->header( "Content-Type", "application/x-www-form-urlencoded" );
        }
        $this->header( "Connection", "close" );
        $this->header( "Transfer-Encoding", "chunked" );
    }

    /**
     * Add an HTTP header
     *
     * @param $header
     * @param $value
     */
    public function header ( $header, $value )
    {
        if( $this->write_started )
        {
            throw new SocketInvalidOperationException( "Can not write header, body writing has started." );
        }
        fwrite( $this->socket, "$header: $value\r\n" );
        fflush( $this->socket );
    }

    /**
     * Write a chunk of data
     * @param $chunk
     */
    public function write ( $chunk )
    {
        if( $this->write_finished )
        {
            throw new SocketInvalidOperationException( "Can not write, reading has started." );
        }

        if( ! $this->write_started )
        {
            fwrite( $this->socket, "\r\n" ); // Start message body
            $this->write_started = true;
        }

        fwrite( $this->socket, sprintf( "%x\r\n", strlen( $chunk ) ) );
        fwrite( $this->socket, $chunk . "\r\n" );
        fflush( $this->socket );
    }

    /**
     * Finish writing chunks and get ready to read.
     */
    public function finishWrite ()
    {
        $this->write("");
        $this->write_finished = true;
    }

    /**
     * Read the socket for a response
     */
    public function read ()
    {
        if( ! $this->write_finished )
        {
            $this->finishWrite();
        }
        $this->read_started = true;

        $response = new AWSResponse();
        while( ! feof( $this->socket ) )
        {
            $response->line( fgets( $this->socket ) );
        }
        $response->complete();
        fclose( $this->socket );

        return $response;
    }
}