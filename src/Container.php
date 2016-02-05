<?php

namespace Styde\Container;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class Container
{

    protected static $instance;

    protected $shared = [];
    protected $bindings = [];

    public static function setInstance(Container $container)
    {
        static::$instance = $container;
    }

    public static function getInstance()
    {
        if (static::$instance == null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function bind($name, $resolver, $shared = false)
    {
        $this->bindings[$name] = [
            'resolver' => $resolver,
            'shared'   => $shared
        ];
    }

    public function instance($name, $object)
    {
        $this->shared[$name] = $object;
    }

    public function singleton($name, $resolver)
    {
        $this->bind($name, $resolver, true);
    }

    public function make($name, array $arguments = array())
    {
        if (!isset ($this->shared[$name]))
        {
            return $this->shared[$name];
        }

        if (isset ($this->bindings[$name])) {
            $resolver = $this->bindings[$name]['resolver'];
            $shared = $this->bindings[$name]['shared'];
        } else {
            $resolver = $name;
            $shared = false;
        }

        if ($resolver instanceof Closure) {
            $object = $resolver($this);
        } else {
            $object = $this->build($resolver, $arguments);
        }

        if ($shared) {
            $this->shared[$name] = $object;
        }

        return $object;
    }

    public function build($name, array $arguments = array())
    {
        $reflection = new ReflectionClass($name);

        if(!$reflection->isInstantiable()) {
            throw new InvalidArgumentException("$name is not instantiable");
        }

        $constructor = $reflection->getConstructor(); //ReflectionMethod

        if(is_null($constructor)) {
            return new $name;
        }

        $constructorParameters = $constructor->getParameters(); //[ReflectionParameter]

        $dependencies = array();

        foreach ($constructorParameters as $constructorParameter) {

            $parameterName = $constructorParameter->getName();

            if (isset ($arguments[$parameterName])) {
                $dependencies[] = $arguments[$parameterName];
                continue;
            }

            try {
                $parameterClass = $constructorParameter->getClass();
            } catch(ReflectionException $e) {
                throw new ContainerException("Unable to build [$name]: " . $e->getMessage(), null, $e);
            }

            if ($parameterClass!=null) {
                $parameterClassName = $parameterClass->getName();
                $dependencies[] = $this->make($parameterClassName);
            } else {
                throw new ContainerException("Please provide the value of the parameter [$parameterName]");
            }
        }

        // new Foo($bar) or MailDummy('url', 'key')
        return $reflection->newInstanceArgs($dependencies);
    }

}