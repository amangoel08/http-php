<?php

namespace ACME;

class PHP_HttpClient {

    /* PHP_HttpClient is a client class for the HTTP protocol. */

    protected $host, $port, $path;
    protected $scheme;
    protected $method;
    protected $postdata = '';
    protected $accept = 'application/json';
    protected $request_headers = array();
    protected $user_agent = "aman/test";
    protected $headers_only = false;


    // * Response vars:
    protected $status;
    protected $headers = array();
    protected $content = '';
    protected $errormsg;

    public function __construct($host, $port=80) {
        $bits   = parse_url($host);
        if(isset($bits['scheme']) && isset($bits['host'])) {
            $host   = $bits['host'];
            $scheme = isset($bits['scheme']) ? $bits['scheme'] : 'http';
            $port   = isset($bits['port']) ? $bits['port'] : 80;
            $path   = isset($bits['path']) ? $bits['path'] : '/';

            if (isset($bits['query']))
                $path .= '?'.$bits['query'];
        }
        $this->host = $host;
        $this->port = $port;
        if(isset($bits['scheme']) && isset($bits['host'])) {
            $this->setScheme($scheme);
            $this->setPath($path);
        }
    }

    public function options($path, $data = null, $headers=array()) {

        /* Executes a OPTION request */

        $this->orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'OPTIONS';
        if ($data) $this->path .= '?'.http_build_query($data);
        $this->setRequestHeaders($headers);
        $result = $this->performOperation();       
        $this->path = $this->orig_path;
        $this->postdata = null;
        return $result;
    }

    public function post($path, $data = null, $headers=array()) {

        /* Executes a POST request for the specified path.*/

        $this->orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'POST';
        $this->setRequestHeaders($headers);
        $this->postdata = $data;

        $result = $this->performOperation();       
        $this->path = $this->orig_path;
        return $result;
    }
    
 
    public function fetchData() {
        return $this->content;
    }

    public function fetchStatusCode() {
        return $this->status;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function fetchErr() {
        return $this->errormsg;
    }

    public function setRequestHeaders($array) {
        foreach($array as $key => $value) {
            $this->request_headers[$key] = $value;
        }
    }

    public function setPath($path) {
        $this->path = $path;
    }

    public function setScheme($scheme) {
        switch($scheme) {
            case 'https':
                $this->scheme = $scheme;
                $this->port = 443;
                break;
            case 'http':
            default:
                $this->scheme = 'http';
        }
    }

    /**
     *  Perform the operation
     * 
     * */

    public function performOperation() {
        
        if($this->scheme=='https') {
            $host = 'ssl://'.$this->host;
            $this->port = 443;
        } else {
            $host = $this->host;
        }

        if (!$fp = @fsockopen($host, $this->port, $errno, $errstr, 20)) {
            switch($errno) {
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                    $this->errormsg .= ' '.$errstr;
                    $this->print_err($this->errormsg);
            }
            return false;
        }

        $request = $this->buildRequest();
        fwrite($fp, trim($request));

        $this->request_headers = array();
        $this->headers = array();
        $this->content = '';
        $this->errormsg = '';

        $__Headers = true;
        $sof = true;

        while (!feof($fp)) {

            $line = fgets($fp, 4096);
            if ($sof) {
                $sof = false;

                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $this->errormsg = "Status code line invalid: ".htmlentities($line);
                    $this->print_err($this->errormsg);
                    return false;
                }

                $this->status = $m[2];

                if($this->status != '200' || $this->status != '202'){
                    $this->print_err('Request failed with Status code:'. $this->status);
                    return false;
                }

                continue;

            }

            if ($__Headers) {

                if (trim($line) == '') {
                    $__Headers = false;
                    if ($this->headers_only) {
                        break;
                    }
                    continue;
                }

                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    continue;
                }

                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
 
                if (isset($this->headers[$key])) {
                    if (is_array($this->headers[$key])) {
                        $this->headers[$key][] = $val;
                    } else {
                        $this->headers[$key] = array($this->headers[$key], $val);
                    }
                } else {
                    $this->headers[$key] = $val;
                }

                continue;

            }

            $this->content .= $line;

        }
 
        fclose($fp);  
        return true;
    }

    /**
     *  Build the query for HTTP Request
     * 
     * */

    protected function buildRequest() {

        $headers = array();
        $headers[] = "{$this->method} {$this->path} HTTP/1.1"; 
        $headers[] = "Host: {$this->host}";

        // * If this is a POST, set the content type and length:
        if(!empty($this->request_headers)) {
            foreach($this->request_headers as $key => $val) {
                if($val===false) {
                    // do nothing
                } else {
                    $headers[] = $key.': '.$val;
                }
            }
        }

        // If it is a POST, add Content-Type.
        if (!isset($this->request_headers['Content-Type']) &&
            $this->method == 'POST') {
            $headers[] = "Content-Type: application/json";
        }

        if (!isset($this->request_headers['User-Agent']))
            $headers[] = "User-Agent: {$this->user_agent}";
        if (!isset($this->request_headers['Accept']))
            $headers[] = "Accept: {$this->accept}";
            
        if ($this->postdata && !isset($this->request_headers['Content-Length'])) {
             $headers[] = 'Content-Length: '.strlen(json_encode($this->postdata));
        }

        $request = implode("\r\n", $headers)."\r\n\r\n".json_encode($this->postdata);
        return $request;

    }

    /**
     *  Print Error Messages
     * 
     * */

    protected function print_err($msg, $object = false) {

        echo '<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>Error:</strong> ' . $msg;

        if ($object)
            echo '<pre>' . htmlspecialchars(print_r($object,true)) . '</pre>';

        echo '</div>';
    }
}
