<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\Configuration;

use Psr\Log\LoggerInterface;

/**
 * Class OverloadedClass
 * @package MyENA\CloudStackClientGenerator\Configuration
 */
class OverloadedClass implements \JsonSerializable
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var string */
    private $overloadedClass;
    /** @var string */
    private $fqName;
    /** @var string|null */
    private $swaggerName;

    /** @var string */
    private $className;

    /**
     * OverloadedClass constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param string $overloadedClass
     * @param string $fqName
     * @param string|null $swaggerName
     */
    public function __construct(LoggerInterface $logger, string $overloadedClass, string $fqName, ?string $swaggerName)
    {
        $this->logger = $logger;
        $this->overloadedClass = $overloadedClass;
        $this->fqName = $fqName;
        $this->swaggerName = $swaggerName;

        // attempt to determine actual classname
        $path = array_filter(array_map('trim', explode('\\', $fqName)));
        $this->className = (string)end($path);
    }

    /**
     * @return string
     */
    public function getOverloadedClass(): string
    {
        return $this->overloadedClass;
    }

    /**
     * @return string
     */
    public function getFQName(): string
    {
        return $this->fqName;
    }

    /**
     * @return string|null
     */
    public function getSwaggerName(): ?string
    {
        return $this->swaggerName;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name'     => $this->getOverloadedClass(),
            'overload' => $this->getFQName(),
            'swagger'  => $this->getSwaggerName(),
        ];
    }
}