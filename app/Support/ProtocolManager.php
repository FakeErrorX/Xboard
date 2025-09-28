<?php

namespace App\Support;

use Illuminate\Contracts\Container\Container;

class ProtocolManager
{
    /**
     * @var Container Laravel container instance
     */
    protected $container;

    /**
     * @var array Cached protocol class list
     */
    protected $protocolClasses = [];

    /**
     * Constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Discover and register all protocol classes
     *
     * @return self
     */
    public function registerAllProtocols()
    {
        if (empty($this->protocolClasses)) {
            $files = glob(app_path('Protocols') . '/*.php');

            foreach ($files as $file) {
                $className = 'App\\Protocols\\' . basename($file, '.php');

                if (class_exists($className) && is_subclass_of($className, AbstractProtocol::class)) {
                    $this->protocolClasses[] = $className;
                }
            }
        }

        return $this;
    }

    /**
     * Get all registered protocol classes
     *
     * @return array
     */
    public function getProtocolClasses()
    {
        if (empty($this->protocolClasses)) {
            $this->registerAllProtocols();
        }

        return $this->protocolClasses;
    }

    /**
     * Get all protocol identifiers
     *
     * @return array
     */
    public function getAllFlags()
    {
        return collect($this->getProtocolClasses())
            ->map(function ($class) {
                try {
                    $reflection = new \ReflectionClass($class);
                    if (!$reflection->isInstantiable()) {
                        return [];
                    }
                    // 'flags' is a public property with a default value in AbstractProtocol
                    $instanceForFlags = $reflection->newInstanceWithoutConstructor();
                    return $instanceForFlags->flags;
                } catch (\ReflectionException $e) {
                    // Log or handle error if a class is problematic
                    report($e);
                    return [];
                }
            })
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Match appropriate protocol handler class name by identifier
     *
     * @param string $flag Request identifier
     * @return string|null Protocol class name or null
     */
    public function matchProtocolClassName(string $flag): ?string
    {
        // In reverse order, giving newer defined protocols higher priority
        foreach (array_reverse($this->getProtocolClasses()) as $protocolClassString) {
            try {
                $reflection = new \ReflectionClass($protocolClassString);

                if (!$reflection->isInstantiable() || !$reflection->isSubclassOf(AbstractProtocol::class)) {
                    continue;
                }

                // 'flags' is a public property in AbstractProtocol
                $instanceForFlags = $reflection->newInstanceWithoutConstructor();
                $flags = $instanceForFlags->flags;

                if (collect($flags)->contains(fn($f) => stripos($flag, (string) $f) !== false)) {
                    return $protocolClassString; // Return class name string
                }
            } catch (\ReflectionException $e) {
                report($e); // Consider logging this error
                continue;
            }
        }
        return null;
    }

    /**
     * Match appropriate protocol handler instance by identifier (legacy logic, if still needed)
     *
     * @param string $flag Request identifier
     * @param array $user User information
     * @param array $servers Server list
     * @param array $clientInfo Client information
     * @return AbstractProtocol|null
     */
    public function matchProtocol($flag, $user, $servers, $clientInfo = [])
    {
        $protocolClassName = $this->matchProtocolClassName($flag);
        if ($protocolClassName) {
            return $this->makeProtocolInstance($protocolClassName, [
                'user' => $user,
                'servers' => $servers,
                'clientName' => $clientInfo['name'] ?? null,
                'clientVersion' => $clientInfo['version'] ?? null
            ]);
        }
        return null;
    }

    /**
     * Generic method for creating protocol instances, compatible with different versions of Laravel container
     * 
     * @param string $class Class name
     * @param array $parameters Constructor parameters
     * @return object Instance
     */
    protected function makeProtocolInstance($class, array $parameters)
    {
        // Laravel's make method can accept an array of parameters as its second argument.
        // These will be used when resolving the class's dependencies.
        return $this->container->make($class, $parameters);
    }
}