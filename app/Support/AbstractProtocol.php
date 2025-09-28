<?php

namespace App\Support;

use App\Services\Plugin\HookManager;

abstract class AbstractProtocol
{
    /**
     * @var array User information
     */
    protected $user;

    /**
     * @var array Server information
     */
    protected $servers;

    /**
     * @var string|null Client name
     */
    protected $clientName;

    /**
     * @var string|null Client version
     */
    protected $clientVersion;

    /**
     * @var array Protocol identifiers
     */
    public $flags = [];

    /**
     * @var array Protocol requirement configuration
     */
    protected $protocolRequirements = [];

    /**
     * @var array Allowed protocol types (whitelist) - no filtering if empty
     */
    protected $allowedProtocols = [];

    /**
     * Constructor
     *
     * @param array $user User information
     * @param array $servers Server information
     * @param string|null $clientName Client name
     * @param string|null $clientVersion Client version
     */
    public function __construct($user, $servers, $clientName = null, $clientVersion = null)
    {
        $this->user = $user;
        $this->servers = $servers;
        $this->clientName = $clientName;
        $this->clientVersion = $clientVersion;
        $this->protocolRequirements = $this->normalizeProtocolRequirements($this->protocolRequirements);
        $this->servers = HookManager::filter('protocol.servers.filtered', $this->filterServersByVersion());
    }

    /**
     * Get protocol identifiers
     *
     * @return array
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Handle request
     *
     * @return mixed
     */
    abstract public function handle();

    /**
     * Filter out servers incompatible with client version
     *
     * @return array
     */
    protected function filterServersByVersion()
    {
        $this->filterByAllowedProtocols();
        $hasGlobalConfig = isset($this->protocolRequirements['*']);
        $hasClientConfig = isset($this->protocolRequirements[$this->clientName]);

        if ((blank($this->clientName) || blank($this->clientVersion)) && !$hasGlobalConfig) {
            return $this->servers;
        }

        if (!$hasGlobalConfig && !$hasClientConfig) {
            return $this->servers;
        }

        return collect($this->servers)
            ->filter(fn($server) => $this->isCompatible($server))
            ->values()
            ->all();
    }

    /**
     * Check if server is compatible with current client
     *
     * @param array $server Server information
     * @return bool
     */
    protected function isCompatible($server)
    {
        $serverType = $server['type'] ?? null;
        if (isset($this->protocolRequirements['*'][$serverType])) {
            $globalRequirements = $this->protocolRequirements['*'][$serverType];
            if (!$this->checkRequirements($globalRequirements, $server)) {
                return false;
            }
        }

        if (!isset($this->protocolRequirements[$this->clientName][$serverType])) {
            return true;
        }

        $requirements = $this->protocolRequirements[$this->clientName][$serverType];
        return $this->checkRequirements($requirements, $server);
    }

    /**
     * Check version requirements
     *
     * @param array $requirements Requirement configuration
     * @param array $server Server information
     * @return bool
     */
    private function checkRequirements(array $requirements, array $server): bool
    {
        foreach ($requirements as $field => $filterRule) {
            if (in_array($field, ['base_version', 'incompatible'])) {
                continue;
            }

            $actualValue = data_get($server, $field);

            if (is_array($filterRule) && isset($filterRule['whitelist'])) {
                $allowedValues = $filterRule['whitelist'];
                $strict = $filterRule['strict'] ?? false;
                if ($strict) {
                    if ($actualValue === null) {
                        return false;
                    }
                    if (!is_string($actualValue) && !is_int($actualValue)) {
                        return false;
                    }
                    if (!isset($allowedValues[$actualValue])) {
                        return false;
                    }
                    $requiredVersion = $allowedValues[$actualValue];
                    if ($requiredVersion !== '0.0.0' && version_compare($this->clientVersion, $requiredVersion, '<')) {
                        return false;
                    }
                    continue;
                }
            } else {
                $allowedValues = $filterRule;
                $strict = false;
            }

            if ($actualValue === null) {
                continue;
            }
            if (!is_string($actualValue) && !is_int($actualValue)) {
                continue;
            }
            if (!isset($allowedValues[$actualValue])) {
                continue;
            }
            $requiredVersion = $allowedValues[$actualValue];
            if ($requiredVersion !== '0.0.0' && version_compare($this->clientVersion, $requiredVersion, '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if current client supports specific features
     *
     * @param string $clientName Client name
     * @param string $minVersion Minimum version requirement
     * @param array $additionalConditions Additional condition checks
     * @return bool
     */
    protected function supportsFeature(string $clientName, string $minVersion, array $additionalConditions = []): bool
    {
        // Check client name
        if ($this->clientName !== $clientName) {
            return false;
        }

        // Check version number
        if (empty($this->clientVersion) || version_compare($this->clientVersion, $minVersion, '<')) {
            return false;
        }

        // Check additional conditions
        foreach ($additionalConditions as $condition) {
            if (!$condition) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter servers by whitelist
     *
     * @return void
     */
    protected function filterByAllowedProtocols(): void
    {
        if (!empty($this->allowedProtocols)) {
            $this->servers = collect($this->servers)
                ->filter(fn($server) => in_array($server['type'], $this->allowedProtocols))
                ->values()
                ->all();
        }
    }

    /**
     * Convert flat protocol requirements to tree structure
     *
     * @param array $flat Flat protocol requirements
     * @return array Tree-structured protocol requirements
     */
    protected function normalizeProtocolRequirements(array $flat): array
    {
        $result = [];
        foreach ($flat as $key => $value) {
            if (!str_contains($key, '.')) {
                $result[$key] = $value;
                continue;
            }
            $segments = explode('.', $key, 3);
            if (count($segments) < 3) {
                $result[$segments[0]][$segments[1] ?? '*'][''] = $value;
                continue;
            }
            [$client, $type, $field] = $segments;
            $result[$client][$type][$field] = $value;
        }
        return $result;
    }
}