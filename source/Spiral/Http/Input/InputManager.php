<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Http\Input;


use Spiral\Core\Component;
use Spiral\Http\MiddlewareInterface;
use Spiral\Http\Request\InputInterface;

class InputManager extends Component implements MiddlewareInterface, InputInterface
{
    /**
     * @var InputBag[]
     */
    private $bagInstances = [];

    /**
     * Associations between bags and representing class/request method.
     *
     * @invisible
     * @var array
     */
    protected $bagAssociations = [
        'headers'    => [
            'class'  => HeadersBag::class,
            'source' => 'getHeaders'
        ],
        'data'       => [
            'class'  => InputBag::class,
            'source' => 'getParsedBody'
        ],
        'query'      => [
            'class'  => InputBag::class,
            'source' => 'getQueryParams'
        ],
        'cookies'    => [
            'class'  => InputBag::class,
            'source' => 'getCookieParams'
        ],
        'files'      => [
            'class'  => FilesBag::class,
            'source' => 'getUploadedFiles'
        ],
        'server'     => [
            'class'  => ServerBag::class,
            'source' => 'getServerParams'
        ],
        'attributes' => [
            'class'  => InputBag::class,
            'source' => 'getAttributes'
        ]
    ];

    /**
     * @invisible
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * InputManager constructor.
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Get active instance of ServerRequestInterface.
     *
     * @return ServerRequestInterface
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * Get UriInterface associated with active request.
     *
     * @return UriInterface
     */
    public function uri()
    {
        return $this->request()->getUri();
    }

    /**
     * Get page path (including leading slash) associated with active request.
     *
     * @return string
     */
    public function path()
    {
        $path = $this->uri()->getPath();
        if (empty($path)) {
            return '/';
        } elseif ($path[0] !== '/') {
            return '/' . $path;
        }

        return $path;
    }

    /**
     * Get HTTP method active request was made with.
     *
     * @return string
     */
    public function method()
    {
        return $this->request()->getMethod();
    }

    /**
     * Check if request was made over http protocol.
     *
     * @return bool
     */
    public function isSecure()
    {
        return $this->request()->getUri()->getScheme() == 'https';
    }

    /**
     * Check if request was made using XmlHttpRequest.
     *
     * @return bool
     */
    public function isAjax()
    {
        return strtolower($this->request()->getHeaderLine('X-Requested-With')) == 'xmlhttprequest';
    }

    /**
     * Client requesting json response by Accept header.
     *
     * @return bool
     */
    public function isJsonExpected()
    {
        return $this->request()->getHeaderLine('Accept') == 'application/json';
    }

    /**
     * Get remove addr resolved from $_SERVER['REMOTE_ADDR']. Will return null if nothing if key not
     * exists.
     *
     * @return string|null
     */
    public function remoteAddress()
    {
        $serverParams = $this->request()->getServerParams();

        return isset($serverParams['REMOTE_ADDR']) ? $serverParams['REMOTE_ADDR'] : null;
    }

    /**
     * Get bag instance or create new one on demand.
     *
     * @param string $name
     * @return InputBag
     */
    public function bag($name)
    {
        if (isset($this->bagInstances[$name])) {
            return $this->bagInstances[$name];
        }

        if (!isset($this->bagAssociations[$name])) {
            throw new \RuntimeException("Undefined input bag '{$name}'.");
        }

        $class = $this->bagAssociations[$name]['class'];
        $data = call_user_func([$this->request(), $this->bagAssociations[$name]['source']]);

        return $this->bagInstances[$name] = new $class($data);
    }

    /**
     * @param string $name
     * @return InputBag
     */
    public function __get($name)
    {
        return $this->bag($name);
    }

    /**
     * @param string      $name
     * @param mixed       $default
     * @param bool|string $implode Implode header lines, false to return header as array.
     * @return mixed
     */
    public function header($name, $default = null, $implode = ',')
    {
        return $this->headers->get($name, $default, $implode);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function data($name, $default = null)
    {
        return $this->data->get($name, $default);
    }

    /**
     * @see data()
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function post($name, $default = null)
    {
        return $this->data($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function query($name, $default = null)
    {
        return $this->query->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function cookie($name, $default = null)
    {
        return $this->cookies->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return UploadedFileInterface|null
     */
    public function file($name, $default = null)
    {
        return $this->files->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function server($name, $default = null)
    {
        return $this->server->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function attribute($name, $default = null)
    {
        return $this->attributes->get($name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($source, $name = null)
    {
        if (!method_exists($this, $source)) {
            throw new InputException("Undefined input source '{$source}'.");
        }

        return call_user_func([$this, $source], $name);
    }
}