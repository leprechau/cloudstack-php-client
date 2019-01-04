<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\API;

use function MyENA\CloudStackClientGenerator\buildRequiredTagLine;
use function MyENA\CloudStackClientGenerator\buildSinceTagLine;
use function MyENA\CloudStackClientGenerator\determineSwaggerName;
use function MyENA\CloudStackClientGenerator\escapeSwaggerString;
use function MyENA\CloudStackClientGenerator\tagIndent;

/**
 * Class Variable
 * @package MyENA\CloudStackClientGenerator\API
 */
class Variable
{
    /** @var string */
    private $name = '';
    /** @var string */
    private $description = '';
    /** @var string */
    private $type = 'string';
    /** @var int */
    private $length = 0;
    /** @var bool */
    private $required = false;
    /** @var string */
    private $since = '0.0';
    /** @var string[] */
    private $related = [];

    /** @var string */
    private $phpdocDescription;

    /** @var bool */
    private $inResponse;

    /**
     * Variable constructor.
     * @param bool $inResponse Whether this property is contained by a response object. If false, assume part of Request object.
     * @param string $name
     */
    public function __construct(bool $inResponse, string $name)
    {
        $this->inResponse = $inResponse;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @param int $length
     */
    public function setLength(int $length)
    {
        $this->length = $length;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @param bool $required
     */
    public function setRequired(bool $required)
    {
        $this->required = $required;
    }

    /**
     * @return string
     */
    public function getSince(): string
    {
        return $this->since;
    }

    /**
     * @param string $since
     */
    public function setSince(string $since)
    {
        $this->since = $since;
    }

    /**
     * @return string[]
     */
    public function getRelated(): array
    {
        return $this->related;
    }

    /**
     * @param string[] $related
     */
    public function setRelated(array $related)
    {
        $this->related = $related;
    }

    /**
     * @param string $related
     */
    public function setRelatedString(string $related)
    {
        $this->related = explode(',', $related);
    }

    /**
     * @return string
     */
    public function getPropertyDocBloc(): string
    {
        $bloc = "    /**\n";
        $bloc .= "{$this->getPHPDocDescription()}\n";
        $bloc .= "     * @var {$this->getPHPTypeTagValue()}\n";
        $bloc .= $this->getSinceTagLine(4, true);
        $bloc .= $this->getRequiredTagLine();
        return $bloc . "\n     */";
    }

    /**
     * @param int $indentLevel
     * @return string
     */
    public function getPHPDocDescription(int $indentLevel = 4): string
    {
        if (!isset($this->phpdocDescription)) {
            $this->phpdocDescription = implode(
                "\n",
                array_map(
                    function ($v) use ($indentLevel) {
                        return sprintf('%s * %s', str_repeat(' ', $indentLevel), $v);
                    },
                    explode("\n",
                        wordwrap(ucfirst($this->getDescription()), 100)
                    )
                )
            );
        }

        return $this->phpdocDescription;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        unset($this->phpdocDescription);
        $this->description = $description;
    }

    /**
     * @return bool
     */
    public function inResponse(): bool
    {
        return $this->inResponse;
    }

    /**
     * @return bool
     */
    public function isDate(): bool
    {
        static $dateTypes = ['date', 'tzdate'];
        return in_array($this->getType(), $dateTypes, true);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function isCollection(): bool
    {
        static $collectionTypes = ['set', 'list', 'map', 'responseobject', 'uservmresponse'];
        return in_array($this->getType(), $collectionTypes, true);
    }

    /**
     * @return string
     */
    public function getPHPType(): string
    {
        $type = $this->getType();
        switch ($type) {

            case 'set':
            case 'list':
            case 'uservmresponse':
            case 'map':
                return 'array';

            case 'responseobject':
                return 'mixed';

            case 'integer':
            case 'long':
            case 'short':
            case 'int':
                return 'integer';

            case 'date':
            case 'tzdate':
                return '\\DateTime';

            case 'object': // TODO: This one might be overly greedy, currently matches "baremetalrcturl"

            case 'imageformat':
            case 'storagepoolstatus':
            case 'hypervisortype':
            case 'status':
            case 'type':
            case 'scopetype':
            case 'state':
            case 'url':
            case 'uuid':
            case 'powerstate':
            case 'outofbandmanagementresponse':
                return 'string';

            // Catch these here so we can analyze outliers easier...
            case 'string':
                return 'string';

            case 'boolean':
                return 'boolean';

            default:
                return $type;
        }
    }

    /**
     * @return string
     */
    public function getPHPTypeTagValue(): string
    {
        if ($this->inResponse()) {
            if ($this->isDate()) {
                return '\\DateTime|string|null Value will try to be parsed as a \\DateTime, falling back to the raw string value if unable';
            }
            if ('jobresult' === $this->getName()) {
                return 'mixed Value will vary between async jobs';
            }
        }

        $tag = $this->getPHPType();
        if ('array' !== $tag && $this->isCollection()) {
            $tag .= '[]';
        }

        return $tag;
    }

    /**
     * @param bool $nullable
     * @param bool $asReturn
     * @return string
     */
    public function getPHPTypeHintValue(bool $nullable = false, bool $asReturn = false): string
    {
        if ($this->inResponse() && 'jobresult' === $this->getName()) {
            return '';
        }

        $hint = '';
        if ($this->isCollection()) {
            $hint = 'array';
        } elseif (!$this->isDate()) {
            switch ($type = $this->getPHPType()) {
                case 'string':
                case 'int':
                    $hint = $type;
                    break;

                case 'double':
                    $hint = 'float';
                    break;

                case 'integer':
                    $hint = 'int';
                    break;

                case 'boolean':
                    $hint = 'bool';
                    break;

            }
        }

        if ('' === $hint) {
            return '';
        }

        if ($nullable) {
            $hint = "?{$hint}";
        }
        return $asReturn ? ": {$hint}" : $hint;
    }

    /**
     * @param int $indent
     * @param bool $newline
     * @return string
     */
    public function getSinceTagLine(int $indent = 4, bool $newline = false): string
    {
        return buildSinceTagLine($this->getSince(), $indent, $newline);
    }

    /**
     * @param int $indent
     * @param bool $newline
     * @return string
     */
    public function getRequiredTagLine(int $indent = 4, bool $newline = false): string
    {
        return buildRequiredTagLine($this->isRequired(), $indent, $newline);
    }

    /**
     * @return string
     */
    public function getValidityCheck()
    {
        // TODO: needs implementing
    }
}