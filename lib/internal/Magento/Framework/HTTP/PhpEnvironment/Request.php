<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\HTTP\PhpEnvironment;

use Zend\Http\Header\HeaderInterface;
use Zend\Stdlib\Parameters;
use Zend\Uri\UriFactory;
use Zend\Uri\UriInterface;

class Request extends \Zend\Http\PhpEnvironment\Request
{
    /**#@+
     * Protocols
     */
    const SCHEME_HTTP  = 'http';
    const SCHEME_HTTPS = 'https';
    /**#@-*/

    /**
     * @var string
     */
    protected $module;

    /**
     * @var string
     */
    protected $controller;

    /**
     * @var string
     */
    protected $action;

    /**
     * PATH_INFO
     *
     * @var string
     */
    protected $pathInfo = '';

    /**
     * @var string
     */
    protected $requestString = '';

    /**
     * Request parameters
     *
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * Has the action been dispatched?
     *
     * @var boolean
     */
    protected $dispatched = false;

    /**
     * @param UriInterface|string|null $uri
     */
    public function __construct($uri = null)
    {
        if (null !== $uri) {
            if (!$uri instanceof UriInterface) {
                $uri = UriFactory::factory($uri);
            }
            if ($uri->isValid()) {
                $path  = $uri->getPath();
                $query = $uri->getQuery();
                if (!empty($query)) {
                    $path .= '?' . $query;
                }
                $this->setRequestUri($path);
            } else {
                throw new \InvalidArgumentException('Invalid URI provided to constructor');
            }
        }
        parent::__construct();
    }

    /**
     * Retrieve the module name
     *
     * @return string
     */
    public function getModuleName()
    {
        return $this->module;
    }

    /**
     * Set the module name to use
     *
     * @param string $value
     * @return $this
     */
    public function setModuleName($value)
    {
        $this->module = $value;
        return $this;
    }

    /**
     * Retrieve the controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        return $this->controller;
    }

    /**
     * Set the controller name to use
     *
     * @param string $value
     * @return $this
     */
    public function setControllerName($value)
    {
        $this->controller = $value;
        return $this;
    }

    /**
     * Retrieve the action name
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->action;
    }

    /**
     * Set the action name
     *
     * @param string $value
     * @return $this
     */
    public function setActionName($value)
    {
        $this->action = $value;
        return $this;
    }

    /**
     * Returns everything between the BaseUrl and QueryString.
     * This value is calculated instead of reading PATH_INFO
     * directly from $_SERVER due to cross-platform differences.
     *
     * @return string
     */
    public function getPathInfo()
    {
        if (empty($this->pathInfo)) {
            $this->setPathInfo();
        }
        return $this->pathInfo;
    }

    /**
     * Set the PATH_INFO string
     *
     * @param string|null $pathInfo
     * @return $this
     */
    public function setPathInfo($pathInfo = null)
    {
        if ($pathInfo === null) {
            $requestUri = $this->getRequestUri();
            if ('/' == $requestUri) {
                return $this;
            }

            // Remove the query string from REQUEST_URI
            $pos = strpos($requestUri, '?');
            if ($pos) {
                $requestUri = substr($requestUri, 0, $pos);
            }

            $baseUrl = $this->getBaseUrl();
            $pathInfo = substr($requestUri, strlen($baseUrl));
            if (!empty($baseUrl) && '/' === $pathInfo) {
                $pathInfo = '';
            } elseif (null === $baseUrl) {
                $pathInfo = $requestUri;
            }
            $this->requestString = $pathInfo . ($pos !== false ? substr($requestUri, $pos) : '');
        }
        $this->pathInfo = (string)$pathInfo;
        return $this;
    }

    /**
     * Get request string
     *
     * @return string
     */
    public function getRequestString()
    {
        return $this->requestString;
    }

    /**
     * Retrieve an alias
     *
     * Retrieve the actual key represented by the alias $name.
     *
     * @param string $name
     * @return string|null Returns null when no alias exists
     */
    public function getAlias($name)
    {
        if (isset($this->aliases[$name])) {
            return $this->aliases[$name];
        }
        return null;
    }

    /**
     * Set a key alias
     *
     * Set an alias used for key lookups. $name specifies the alias, $target
     * specifies the actual key to use.
     *
     * @param string $name
     * @param string $target
     * @return $this
     */
    public function setAlias($name, $target)
    {
        $this->aliases[$name] = $target;
        return $this;
    }

    /**
     * Get an action parameter
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        $key = (string) $key;
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;
        if (isset($this->params[$keyName])) {
            return $this->params[$keyName];
        } elseif ($value = $this->getQuery($keyName)) {
            return $value;
        } elseif ($value = $this->getPost($keyName)) {
            return $value;
        }
        return $default;
    }

    /**
     * Set an action parameter
     *
     * A $value of null will unset the $key if it exists
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setParam($key, $value)
    {
        $key = (string) $key;
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;
        if ((null === $value) && isset($this->params[$keyName])) {
            unset($this->params[$keyName]);
        } elseif (null !== $value) {
            $this->params[$keyName] = $value;
        }
        return $this;
    }

    /**
     * Get all action parameters
     *
     * @return array
     */
    public function getParams()
    {
        $params = $this->params;
        if ($this->isGet()) {
            $params += $this->getQuery()->toArray();
        }
        if ($this->isPost()) {
            $params += $this->getPost()->toArray();
        }
        return $params;
    }

    /**
     * Set action parameters en masse; does not overwrite
     *
     * Null values will unset the associated key.
     *
     * @param array $array
     * @return $this
     */
    public function setParams(array $array)
    {
        $this->params = $this->params + (array) $array;
        foreach ($array as $key => $value) {
            if (null === $value) {
                unset($this->params[$key]);
            }
        }
        return $this;
    }

    /**
     * Unset all user parameters
     *
     * @return $this
     */
    public function clearParams()
    {
        $this->params = [];
        return $this;
    }

    /**
     * Get the request URI scheme
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->getUri()->getScheme();
    }

    /**
     * Set flag indicating whether or not request has been dispatched
     *
     * @param boolean $flag
     * @return $this
     */
    public function setDispatched($flag = true)
    {
        $this->dispatched = $flag ? true : false;
        return $this;
    }

    /**
     * Determine if the request has been dispatched
     *
     * @return boolean
     */
    public function isDispatched()
    {
        return $this->dispatched;
    }

    /**
     * Is https secure request
     *
     * @return bool
     */
    public function isSecure()
    {
        return ($this->getScheme() == self::SCHEME_HTTPS);
    }

    /**
     * Set POST parameters
     *
     * @param string|array $name
     * @param mixed $value
     * @return $this
     */
    public function setPostValue($name, $value = null)
    {
        if (is_array($name)) {
            parent::setPost(new Parameters($name));
            return $this;
        }
        $this->getPost()->set($name, $value);
        return $this;
    }

    /**
     * Access values contained in the superglobals as public members
     * Order of precedence: 1. GET, 2. POST, 3. COOKIE, 4. SERVER, 5. ENV
     *
     * @see http://msdn.microsoft.com/en-us/library/system.web.httprequest.item.aspx
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        switch (true) {
            case isset($this->params[$key]):
                return $this->params[$key];

            case ($value = $this->getQuery($key)):
                return $value;

            case ($value = $this->getPost($key)):
                return $value;

            case isset($_COOKIE[$key]):
                return $_COOKIE[$key];

            case ($key == 'REQUEST_URI'):
                return $this->getRequestUri();

            case ($key == 'PATH_INFO'):
                return $this->getPathInfo();

            case ($value = $this->getServer($key)):
                return $value;

            case ($value = $this->getEnv($key)):
                return $value;

            default:
                return null;
        }
    }

    /**
     * Alias to __get
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->__get($key);
    }

    /**
     * Check to see if a property is set
     *
     * @param string $key
     * @return boolean
     */
    public function __isset($key)
    {
        switch (true) {
            case isset($this->params[$key]):
                return true;

            case ($value = $this->getQuery($key)):
                return true;

            case ($value = $this->getPost($key)):
                return true;

            case isset($_COOKIE[$key]):
                return true;

            case ($value = $this->getServer($key)):
                return true;

            case ($value = $this->getEnv($key)):
                return true;

            default:
                return false;
        }
    }

    /**
     * Alias to __isset()
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        return $this->__isset($key);
    }

    /**
     * Get all headers of a certain name/type.
     *
     * @param string $name Header name to retrieve.
     * @param mixed|null $default Default value to use when the requested header is missing.
     * @return bool|HeaderInterface
     */
    public function getHeader($name, $default = false)
    {
        $header = parent::getHeader($name, $default);
        if ($header instanceof HeaderInterface) {
            return $header->getFieldValue();
        }
        return false;
    }

    /**
     * Retrieve HTTP HOST
     *
     * @param bool $trimPort
     * @return string
     *
     * @todo getHttpHost should return only string (currently method return boolean value too)
     */
    public function getHttpHost($trimPort = true)
    {
        $httpHost = $this->getServer('HTTP_HOST');
        if (empty($httpHost)) {
            return false;
        }
        if ($trimPort) {
            $host = explode(':', $httpHost);
            return $host[0];
        }
        return $httpHost;
    }

    /**
     * Get the client's IP addres
     *
     * @param  boolean $checkProxy
     * @return string
     */
    public function getClientIp($checkProxy = true)
    {
        if ($checkProxy && $this->getServer('HTTP_CLIENT_IP') != null) {
            $ip = $this->getServer('HTTP_CLIENT_IP');
        } else if ($checkProxy && $this->getServer('HTTP_X_FORWARDED_FOR') != null) {
            $ip = $this->getServer('HTTP_X_FORWARDED_FOR');
        } else {
            $ip = $this->getServer('REMOTE_ADDR');
        }
        return $ip;
    }
}
