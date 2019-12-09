<?php

/**
 * PHP class to handle connections with cPanel's UAPI specifically through WordPress's HTTP api
 *
 * For documentation on cPanel's UAPI:
 * @see https://documentation.cpanel.net/display/SDK/UAPI+Functions
 *
 * Written to support WordPress HTTP-api by DigiTimber, December 2019
 */

/**
 * Class DTWP_HTTP_cPanelAPI
 */
class DTcPanelAPI
{
    public $version = '1.0';
    public $port = 2083; //default for cpanel SSL (2087 for WHM SSL)
    public $server = "127.0.0.1"; //default to localhost
    public $user;
    public $json = '';

    protected $module; //String - Module we want to use
    protected $auth;
    protected $pass;
    protected $type;
    protected $session;
    protected $method;
    protected $requestUrl;
    protected $postData = '';


    /**
     * @param $user
     * @param $pass
     * @param $server
     */
    function __construct($user, $pass, $server)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->server = $server;
    }

    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'get':
                $this->httpMethod = 'GET';
                break;
            case 'post':
                $this->httpMethod = 'POST';
                break;
            default:
                $this->module = $name;
        }
        return $this;
    }

    /**
     * Magic __toSting() method, allows us to return the result as raw json
     * @return mixed
     */
    public function __toString()
    {
        return $this->json;
    }

    /**
     * Magic __call method, will translate all function calls to object to API requests
     * @param $name - name of the function
     * @param $arguments - an array of arguments
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        if (count($arguments) < 1 || !is_array($arguments[0]))
            $arguments[0] = [];
        $this->json = $this->APIcall($name, $arguments[0]);
        return json_decode($this->json);
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool|mixed
     */
    protected function APIcall($name, $arguments)
    {
        $this->auth = base64_encode($this->user . ":" . $this->pass);
        $this->requestUrl = 'https://' . $this->server . ':' . $this->port . '/execute/';
        $this->requestUrl .= ($this->module != '' ? $this->module . "/" : '') . $name . '?';
        if($this->httpMethod == 'GET') {
            $this->requestUrl .= http_build_query($arguments);
        }
        if($this->httpMethod == 'POST'){
            $this->postData = $arguments;
        }

        return $this->wp_http_api_request($this->requestUrl);
    }

    protected function wp_http_api_request($requestUrl) {
        $args = array(
                'headers' => array("Authorization" => "Basic ".$this->auth),
                'sslverify' => false
        );

        $response = wp_remote_retrieve_body(wp_remote_get($this->requestUrl, $args));
        return $response;

    }
}
