<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\API;

use MyENA\CloudStackClientGenerator\Configuration\OverloadedClass;

/**
 * Class ObjectVariable
 * @package MyENA\CloudStackClientGenerator\API
 */
class ObjectVariable extends Variable
{
    /** @var string */
    private $namespace;
    /** @var VariableContainer */
    private $properties;
    /** @var bool */
    private $shared;

    /** @var \MyENA\CloudStackClientGenerator\Configuration\OverloadedClass */
    private $overloadedClass;

    /**
     * ObjectVariable constructor.
     * @param bool $inResponse
     * @param string $name
     * @param string $namespace
     * @param bool $shared
     */
    public function __construct(bool $inResponse, string $name, string $namespace, bool $shared)
    {
        parent::__construct($inResponse, $name);
        $this->namespace = $namespace;
        $this->properties = new VariableContainer();
        $this->shared = $shared;
    }

    /**
     * @return \MyENA\CloudStackClientGenerator\Configuration\OverloadedClass|null
     */
    public function getOverloadedClass(): ?OverloadedClass
    {
        return $this->overloadedClass ?? null;
    }

    /**
     * @param \MyENA\CloudStackClientGenerator\Configuration\OverloadedClass|null $overloadedClass
     */
    public function setOverloadedClass(?OverloadedClass $overloadedClass): void
    {
        $this->overloadedClass = $overloadedClass;
    }

    /**
     * @return \MyENA\CloudStackClientGenerator\API\VariableContainer
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        if ($this->isShared()) {
            return ucfirst($this->getName());
        }

        return ucfirst($this->getName()) . 'Response';
    }

    /**
     * @return bool
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    /**
     * @inheritDoc
     */
    public function getPHPType(): string
    {
        if ($overloaded = $this->getOverloadedClass()) {
            return $overloaded->getFQName();
        }
        return $this->getFQName();
    }

    /**
     * @return string
     */
    public function getFQName(): string
    {
        if (!isset($this->namespace) || $this->namespace === '') {
            return sprintf('\\CloudStackResponse\\%s', $this->getClassName());
        }
        return sprintf('\\%s\\CloudStackResponse\\%s', $this->namespace, $this->getClassName());
    }

    /**
     * @return string
     */
    public function getSwaggerName(): string
    {
        return "CloudStack{$this->getClassName()}";
    }
}