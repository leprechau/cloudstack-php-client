<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\Configuration\Environment;

/**
 * Class Swagger
 * @package MyENA\CloudStackClientGenerator\Configuration\Environment
 */
class Swagger implements \JsonSerializable
{
    const DEFAULT_VERSION    = 2;
    const ALLOWABLE_VERSIONS = [2, 3];
    const FIELD_VERSION      = 'version';

    /** @var int */
    private $version = self::DEFAULT_VERSION;

    /**
     * Swagger constructor.
     * @param array $swaggerConfig
     */
    public function __construct(array $swaggerConfig)
    {
        if (isset($swaggerConfig[self::FIELD_VERSION])) {
            $version = (int)$swaggerConfig[self::FIELD_VERSION];
            if (in_array($version, self::ALLOWABLE_VERSIONS, true)) {
                $this->version = $version;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'swagger.version accepts value of 2 or 3, %s seen', $swaggerConfig[self::FIELD_VERSION]
                ));
            }
        }
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [self::FIELD_VERSION => $this->getVersion()];
    }
}