<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\Configuration\Environment;

/**
 * Class CommandCache
 * @package MyENA\CloudStackClientGenerator\Configuration\Environment
 */
class CommandCache
{
    /** @var string */
    private $name;
    /** @var bool */
    private $enabled;
    /** @var int */
    private $ttl;

    /**
     * CommandCache constructor.
     * @param string $name
     * @param array $config
     */
    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;
        $this->enabled = (bool)($config['enabled'] ?? true);
        $this->ttl = (int)($config['ttl'] ?? Cache::DEFAULT_TTL);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return int
     */
    public function getTTL(): int
    {
        return $this->ttl;
    }
}