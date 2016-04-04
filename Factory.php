<?php
namespace core\view;

use ArrayAccess;
use BadMethodCallException;
use core\view\Engines\EngineInterface;

class Factory implements ArrayAccess
{
    /**
     * The view view instance.
     *
     * @var \core\view\View
     */
    protected $view;

    /**
     * The engine implementation.
     *
     * @var \core\view\Engines\EngineInterface
     */
    protected $engine;

    /**
     * The name of the view.
     *
     * @var string
     */
    protected $factory;

    /**
     * The array of view data.
     *
     * @var array
     */
    protected $data;

    /**
     * The path to the view file.
     *
     * @var string
     */
    protected $path;

    /**
     * Create a new view instance.
     *
     * @param  \core\view\View $view
     * @param  \core\view\Engines\EngineInterface $engine
     * @param  string $view
     * @param  string $path
     * @param  array $data
     * @return void
     */
    public function __construct(View $view, EngineInterface $engine, $factory, $path, $data = [])
    {
        $this->factory = $factory;
        $this->path = $path;
        $this->engine = $engine;
        $this->view = $view;

        $this->data = (array)$data;
    }

    /**
     * Get the string contents of the view.
     *
     * @param  callable|null $callback
     * @return string
     */
    public function render(callable $callback = null)
    {
        $contents = $this->renderContents();

        $response = isset($callback) ? call_user_func($callback, $this, $contents) : null;

        // Once we have the contents of the view, we will flush the sections if we are
        // done rendering all views so that there is nothing left hanging over when
        // another view gets rendered in the future by the application developer.
        $this->view->flushSectionsIfDoneRendering();

        if (config('app.token_on')) {
            //获取令牌
            if (!Session::has(config('app.token_name'))) {
                session(config('app.token_name'),md5(time() . mt_rand(1, 999)));
            }
            //表单添加令牌
            if (preg_match_all('/<form.*?>(.*?)<\/form>/is', $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $id => $m) {
                    $php = "<input type='hidden' name='" . config('app.token_name') . "' value='" . session(config('app.token_name')) . "'>";
                    $contents = str_replace($m[1], $m[1] . $php, $contents);
                }
            }
        }

        echo !is_null($response) ? $response : $contents;
    }

    /**
     * Get the contents of the view instance.
     *
     * @return string
     */
    protected function renderContents()
    {
        // We will keep track of the amount of views being rendered so we can flush
        // the section after the complete rendering operation is done. This will
        // clear out the sections for any separate views that may be rendered.
        $this->view->incrementRender();

        $contents = $this->getContents();

        // Once we've finished rendering the view, we'll decrement the render count
        // so that each sections get flushed out next time a view is created and
        // no old sections are staying around in the memory of an environment.
        $this->view->decrementRender();

        return $contents;
    }

    /**
     * Get the sections of the rendered view.
     *
     * @return array
     */
    public function renderSections()
    {
        return $this->render(function () {
            return $this->view->getSections();
        });
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @return string
     */
    protected function getContents()
    {
        return $this->engine->get($this->path, $this->gatherData());
    }

    /**
     * Get the data bound to the view instance.
     *
     * @return array
     */
    protected function gatherData()
    {
        $data = array_merge($this->view->getShared(), $this->data);

        foreach ($data as $key => $value) {
            if ($value instanceof Renderable) {
                $data[$key] = $value->render();
            }
        }

        return $data;
    }

    /**
     * Add a piece of data to the view.
     *
     * @param  string|array $key
     * @param  mixed $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Add a view instance to the view data.
     *
     * @param  string $key
     * @param  string $view
     * @param  array $data
     * @return $this
     */
    public function nest($key, $factory, array $data = [])
    {
        return $this->with($key, $this->view->make($factory, $data));
    }

    /**
     * Get the view view instance.
     *
     * @return \core\view\View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * Get the view's rendering engine.
     *
     * @return \core\view\Engines\EngineInterface
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function name()
    {
        return $this->getName();
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function getName()
    {
        return $this->factory;
    }

    /**
     * Get the array of view data.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the path to the view file.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the path to the view.
     *
     * @param  string $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Determine if a piece of data is bound.
     *
     * @param  string $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get a piece of bound data to the view.
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->data[$key];
    }

    /**
     * Set a piece of data on the view.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->with($key, $value);
    }

    /**
     * Unset a piece of data from the view.
     *
     * @param  string $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Get a piece of data from the view.
     *
     * @param  string $key
     * @return mixed
     */
    public function &__get($key)
    {
        return $this->data[$key];
    }

    /**
     * Set a piece of data on the view.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->with($key, $value);
    }

    /**
     * Check if a piece of data is bound to the view.
     *
     * @param  string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a piece of bound data from the view.
     *
     * @param  string $key
     * @return bool
     */
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Dynamically bind parameters to the view.
     *
     * @param  string $method
     * @param  array $parameters
     * @return \core\view\View
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (strpos($method, 'with') === 0) {
            $value = substr($method, 4);

            if (!ctype_lower($value)) {
                $value = preg_replace('/\s+/', '', $value);
                $value = strtolower(preg_replace('/(.)(?=[A-Z])/', '$1' . '_', $value));
            }

            return $this->with($value, $parameters[0]);
        }

        throw new BadMethodCallException("Method [$method] does not exist on view.");
    }

    /**
     * Get the string contents of the view.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}
