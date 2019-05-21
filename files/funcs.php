<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator;

use DCarbone\Go\Time;
use MyENA\CloudStackClientGenerator\API\ObjectVariable;
use MyENA\CloudStackClientGenerator\API\Variable;
use MyENA\CloudStackClientGenerator\API\VariableContainer;
use MyENA\CloudStackClientGenerator\Configuration\Environment;

/**
 * @param string $in
 * @return string
 */
function cleanKey(string $in): string
{
    if (false === strpos($in, '_')) {
        return $in;
    }
    $s = explode('_', $in);
    if (1 === count($s)) {
        return $s[0];
    }
    return $s[0] . implode('', array_map('ucfirst', array_splice($s, 1)));
}

/**
 * @param int $leading
 * @param int $trailing
 * @return string
 */
function tagIndent(int $leading, int $trailing = 0): string
{
    return str_repeat(' ', $leading) . ' * ' . ($trailing > 0 ? str_repeat(' ', $trailing) : '');
}

/**
 * @param string $in
 * @return string
 */
function escapeSwaggerString(string $in): string
{
    return str_replace('"', '""', $in);
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable $variable
 * @return string
 */
function buildSwaggerRefValue(Environment $environment, ObjectVariable $variable): string
{
    if (2 === $environment->getSwagger()->getVersion()) {
        return "#/definitions/{$variable->getSwaggerName()}";
    } else {
        return "#/components/schemas/{$variable->getSwaggerName()}";
    }
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param \MyENA\CloudStackClientGenerator\API\Variable $variable
 * @return string
 */
function buildSwaggerItemsTag(Environment $environment, Variable $variable): string
{
    // TODO: This will need to be updated to properly model things like details maps and request tags...
    if (2 === $environment->getSwagger()->getVersion()) {
        return ($variable instanceof ObjectVariable) ?
            '@SWG\\Items(ref="' . buildSwaggerRefValue($environment, $variable) . '")' :
            "@SWG\\Items(type=\"string\")";
    } else {
        return ($variable instanceof ObjectVariable) ?
            '@OA\\Items(ref="' . buildSwaggerRefValue($environment, $variable) . '")' :
            "@OA\\Items(type=\"string\")";
    }
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param \MyENA\CloudStackClientGenerator\API\Variable $variable
 * @param bool $trailingComma
 * @return string
 */
function buildSwaggerDescriptionField(
    Environment $environment,
    Variable $variable,
    bool $trailingComma = false
): string {
    if ('' === $variable->getDescription()) {
        return '';
    }
    return 'description="' .
        ucfirst(escapeSwaggerString($variable->getDescription())) .
        '"' .
        ($trailingComma ? ',' : '');
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param \MyENA\CloudStackClientGenerator\API\Variable $variable
 * @param bool $trailingComma
 * @param bool $inline
 * @param int $indent
 * @param int $nestLevel
 * @return string
 */
function buildSwaggerTypeField(
    Environment $environment,
    Variable $variable,
    bool $trailingComma = false,
    bool $inline = false,
    int $indent = 4,
    int $nestLevel = 1
): string {
    $parts = [];
    if ($variable->isCollection()) {
        $parts[] = 'type="array"';
        $parts[] = buildSwaggerItemsTag($environment, $variable);
    } elseif ($variable instanceof ObjectVariable) {
        $parts[] = 'type="object"';
        $parts[] = 'ref="' . buildSwaggerRefValue($environment, $variable) . '"';
    } elseif ('mixed' === $variable->getPHPType() || $variable->isDate()) {
        $parts[] = 'type="string"';
    } else {
        $parts[] = 'type="' . $variable->getPHPType() . '"';
    }

    if ($inline) {
        return implode(',', $parts) . ($trailingComma ? ',' : '');
    }

    $tag = '';
    foreach ($parts as $i => $part) {
        if ($i > 0) {
            $tag .= ",\n";
        }
        $tag .= tagIndent($indent, $nestLevel * 4) . $part;
    }

    return $tag . ($trailingComma ? ',' : '');
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param \MyENA\CloudStackClientGenerator\API\Variable $variable
 * @param bool $inline
 * @param int $indent
 * @param int $nestLevel
 * @return string
 */
function buildSwaggerPropertyTag(
    Environment $environment,
    Variable $variable,
    bool $inline,
    int $indent,
    int $nestLevel
): string {
    $swg2 = (2 === $environment->getSwagger()->getVersion());
    if ($inline) {
        if (2 === $environment->getSwagger()->getVersion()) {
            $format = '@SWG\\Property(property="%s",%s%s)';
        } else {
            $format = '@OA\\Property(property="%s",%s%s)';
        }
        return sprintf(
            $format,
            $variable->getName(),
            buildSwaggerTypeField($environment, $variable, true),
            buildSwaggerDescriptionField($environment, $variable, false)
        );
    }
    $tag = tagIndent($indent, $nestLevel * 4) . ($swg2 ? "@SWG\\Property(\n" : "@OA\\Property(\n");
    $tag .= tagIndent($indent, ($nestLevel + 1) * 4) . "property=\"" . determineSwaggerName($variable) . "\",\n";
    $tag .= buildSwaggerTypeField($environment, $variable, true, false, $indent, $nestLevel + 1) . "\n";
    $tag .= tagIndent($indent, ($nestLevel + 1) * 4) .
        buildSwaggerDescriptionField($environment, $variable, false) .
        "\n";

    return $tag . tagIndent($indent, $nestLevel * 4) . ')';
}

/**
 * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
 * @param string $swaggerName
 * @param string $description
 * @param \MyENA\CloudStackClientGenerator\API\VariableContainer $variables
 * @param int $indent
 * @param bool $newline
 * @return string
 */
function buildSwaggerDefinitionTag(
    Environment $environment,
    string $swaggerName,
    string $description,
    VariableContainer $variables,
    int $indent,
    bool $newline
): string {
    $description = escapeSwaggerString($description);
    if (2 === $environment->getSwagger()->getVersion()) {
        $tag = tagIndent($indent) . "@SWG\\Definition(\n";
        $tag .= tagIndent($indent, 4) . "definition=\"{$swaggerName}\",\n";
    } else {
        $tag = tagIndent($indent) . "@OA\\Schema(\n";
        $tag .= tagIndent($indent, 4) . "schema=\"{$swaggerName}\",\n";
    }
    $tag .= tagIndent($indent, 4) . "type=\"object\",\n";
    $tag .= tagIndent($indent, 4) . "description=\"{$description}\",\n";

    if (0 < count($required = $variables->getRequired())) {
        $names = [];
        foreach ($required as $var) {
            $names[] = $var->getName();
        }
        $tag .= tagIndent($indent, 4) . 'required={"' . implode('","', $names) . "\"},\n";
    }

    foreach ($variables as $variable) {
        $tag .= buildSwaggerPropertyTag($environment, $variable, false, $indent, 1) . ",\n";
    }

    return rtrim($tag, "\n,") . "\n" . tagIndent($indent) . ')' . ($newline ? "\n" : '');
}

/**
 * @param string $since
 * @param int $indent
 * @param bool $newline
 * @return string
 */
function buildSinceTagLine(string $since, int $indent = 4, bool $newline = false): string
{
    if ('0.0' === $since) {
        return '';
    }
    return tagIndent($indent) . '@since ' . $since . ($newline ? "\n" : '');
}

/**
 * @param bool $required
 * @param int $indent
 * @param bool $newline
 * @return string
 */
function buildRequiredTagLine(bool $required, int $indent = 4, bool $newline = false): string
{
    return tagIndent($indent) . '@' . ($required ? 'required' : 'optional') . ($newline ? "\n" : '');
}

/**
 * PHPDoc Tag helper func
 *
 * TODO: needs more work to be really useful, leaving the stub.
 *
 * @param string $tagName Name of tag
 * @param string $tagValue Value of tag
 * @param bool $annotation Is this tag an annotation or not.  If true, will wrap output in parenthesis
 * @param int $indentLevel Number of spaces to prefix per output line
 * @param bool $trailingNewline Append a \n character to output
 * @return string
 */
function buildTag(
    string $tagName,
    string $tagValue,
    bool $annotation = false,
    int $indentLevel = 4,
    bool $trailingNewline = false
): string {

    $vlen = strlen($tagValue);

    $tag = sprintf('%s * @%s%s', tagIndent($indentLevel), $tagName, ($annotation ? '(' : ''));

    // if this is just an empty value tag
    if (0 === $vlen) {
        return sprintf(
            '%s%s%s',
            $tag,
            ($annotation ? ')' : ''),
            ($trailingNewline ? "\n" : '')
        );
    }

    return sprintf(
        '%s%s%s%s',
        $tag,
        $tagValue,
        ($annotation ? ')' : ''),
        ($trailingNewline ? "\n" : '')
    );
}

/**
 * @param string $in
 * @return string
 */
function tryResolvePath(string $in): string
{
    if (0 === strpos($in, './')) {
        if ($rp = realpath(PHP_CLOUDSTACK_GENERATOR_ROOT . '/' . substr($in, 2))) {
            return $rp;
        }
        return PHP_CLOUDSTACK_GENERATOR_ROOT . '/' . substr($in, 2);
    } elseif (0 !== strpos($in, '/')) {
        if ($rp = realpath(PHP_CLOUDSTACK_GENERATOR_ROOT . '/' . ltrim($in, "/"))) {
            return $rp;
        }
        return PHP_CLOUDSTACK_GENERATOR_ROOT . '/' . ltrim($in, "/");
    } else {
        return $in;
    }
}

/**
 * @param $in
 * @return int
 */
function parseTTL($in): int
{
    if (is_int($in)) {
        return $in;
    } elseif (is_string($in)) {
        if (ctype_digit($in)) {
            return (int)$in;
        } else {
            return (int)Time::ParseDuration($in)->Seconds();
        }
    } else {
        throw new \InvalidArgumentException(sprintf(
            'TTL values must be either an integer value or a string Duration value following the Golang Duration spec https://godoc.org/time#Duration.  %s seen.',
            gettype($in)
        ));
    }
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable $obj
 * @return array
 */
function findImports(ObjectVariable $obj): array
{
    $imports = [];
    if ($overload = $obj->getOverloadedClass()) {
        $imports[] = ltrim($overload->getFQName(), "\\");
    }
    foreach ($obj->getProperties() as $property) {
        if ($property instanceof ObjectVariable) {
            $imports = array_merge($imports, findImports($property));
        }
    }
    return $imports;
}

/**
 * @param \stdClass $capabilities
 * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable|null $obj
 * @return string
 */
function buildFileHeader(\stdClass $capabilities, ?ObjectVariable $obj = null): string
{
    $header = <<<STRING
/*
 * This file was autogenerated as part of the CloudStack PHP Client generate script
 *
 * (c) Quentin Pleplé <quentin.pleple@gmail.com>
 * (c) Aaron Hurt <ahurt@anbcs.com>
 * (c) Nathan Johnson <nathan@nathanjohnson.org>
 * (c) Daniel Carbone <daniel.p.carbone@gmail.com>
 * (c) Bogdan Gabor <bgabor@ena.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
STRING;

    if ($obj) {
        $imports = [];
        foreach ($obj->getProperties() as $property) {
            if ($property instanceof ObjectVariable) {
                $imports = array_merge($imports, findImports($property));
            }
        }
        if (0 < count($imports)) {
            $header .= "\n\n";
            $header .= implode(
                "\n",
                array_map(
                    function (string $in): string {
                        return "use {$in};";
                    },
                    $imports
                )
            );
        }
    }

    return $header;
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable $obj
 * @param bool $fq
 * @return string
 */
function determineClass(ObjectVariable $obj, bool $fq = false): string
{
    if ($overloaded = $obj->getOverloadedClass()) {
        return $fq ? $overloaded->getFQName() : $overloaded->getClassName();
    }
    return $fq ? $obj->getFQName() : $obj->getClassName();
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\Variable $var
 * @return string
 */
function determineSwaggerName(Variable $var): string
{
    if ($var instanceof ObjectVariable) {
        if (($overloaded = $var->getOverloadedClass()) && null !== ($sname = $overloaded->getSwaggerName())) {
            return $sname;
        }
        return $var->getSwaggerName();
    }
    return $var->getName();
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable $obj
 * @param \MyENA\CloudStackClientGenerator\API|null $api
 * @return string
 */
function objectConstructor(ObjectVariable $obj, ?API $api): string
{
    $c = <<<STRING
    /**
     * {$obj->getClassName()} Constructor
     *

STRING;
    if ($api && ($api->isPageable() || $api->isList())) {
        if ($api->isPageable()) {
            $c .= <<<STRING
     * @param int \$requestPage
     * @param int \$requestPageSize
     * @param int \$totalReturnCount
     * @param array \$data    
     */
    public function __construct(?int \$requestPage, ?int \$requestPageSize, int \$totalReturnCount, array \$data) {

STRING;
        } else {
            $c .= <<<STRING
     * @param int \$totalReturnCount
     * @param array \$data    
     */
    public function __construct(int \$totalReturnCount, array \$data) {

STRING;
        }
    } else {
        $c .= <<<STRING
     * @param array \$data
     */
    public function __construct(array \$data) {

STRING;

    }

    // TODO: This could stand to be improved.

    foreach ($obj->getProperties() as $property) {
        $const = $property->getFieldConstantName(false);
        $name = $property->getName();

        if ('date' === $property->getType()) {
            if ($property->isCollection()) {
                $c .= <<<EOT
        if (isset(\$data[self::{$const}])) {
            if (is_string(\$data[self::{$const}])) {
                if ('' === (\$v = trim(\$data[self::{$const}]))) {
                    \$this->{$name} = [Types\\DateType::fromApiDate(\$v)];
                }
            } elseif (is_object(\$data[self::{$const}])) {
                if (\$data[self::{$const}] instanceof Types\\DateType) {
                    \$this->{$name} = [\$data[self::{$const}]];
                } else {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" values must be proper formatted string or instance of "%s", object of type "%s" seen.',
                        Types\\DateType::class,
                        get_class(\$data[self::{$const}])
                    ));
                }
            } elseif (!is_array(\$data[self::{$const}])) {
                throw new \\InvalidArgumentException(sprintf(
                    'Field "{$name}" expected to be array of date values, "%s" seen.',
                    gettype(\$data[self::{$const}])
                ));
            } else {
                \${$name} = [];
                foreach(\$data[self::{$const}] as \$i => \$d) {
                    if (null === \$d) {
                        continue;
                    }
                    if (is_object(\$d)) {
                        if (\$d instanceof Types\\DateType) {
                            \${$name}[] = \$d;
                        } else {
                            throw new \\InvalidArgumentException(sprintf(
                                'Field "{$name}" values must be proper formatted string or instance of "%s", offset "%d" has object of type "%s" seen.',
                                Types\\DateType::class,
                                \$i,
                                get_class(\$d)
                            ));
                        }
                    } elseif (is_string(\$d)) {
                        if ('' !== (\$d = trim(\$d))) {
                            \${$name}[] = Types\\DateType::fromApiDate(\$v);
                        }                    
                    } else {
                        throw new \InvalidArgumentException(sprintf(
                            'Field "{$name}" values must be proper formatted string or instance of "%s", offset "%d" value of type "%s" seen.',
                            Types\\DateType::class,
                            \$i,
                            gettype(\$d)
                        ));
                    }
                }
                \$this->{$name} = ([] === \${$name}) ? null : \${$name};
            }
        }

EOT;
            } else {
                $c .= <<<EOT
        if (isset(\$data[self::{$const}])) {
            if (is_object(\$data[self::{$const}])) {
                if (!(\$data[self::{$const}] instanceof Types\\DateType)) {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" values must be proper formatted string or instance of "%s", object of type "%s" seen.',
                        Types\\DateType::class,
                        get_class(\$data[self::{$const}])
                    ));
                }
                \$this->{$name} = \$data[self::{$const}];
            } elseif (is_string(\$data[self::{$const}])) {
                if ('' !== (\$v = trim(\$data[self::{$const}]))) {
                    \$this->{$name} = Types\\DateType::fromApiDate(\$v);                
                }
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Field "{$name}" values must be proper formatted string or instance of "%s", type "%s" seen.',
                    Types\\DateType::class,
                    gettype(\$data[self::{$const}])
                ));
            }
        }

EOT;
            }
        } elseif ($property instanceof ObjectVariable) {
            $className = determineClass($property);
            if ($property->isCollection()) {
                $c .= <<<EOT
        if (isset(\$data[self::{$const}])) {
            if (is_object(\$data[self::{$const}])) {
                if (!(\$data[self::{$const}] instanceof {$className})) {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" must be instance of "%s", array descriptor, or array of objects or descriptors, single object of class "%s" seen.',
                        {$className}::class,
                        get_class(\$data[self::{$const}]) 
                    ));
                }
                \$this->{$name} = [\$data[self::{$const}]];
            } elseif (is_array(\$data[self::{$const}])) {
                if (is_string(key(\$data[self::{$const}]))) {
                    \$this->{$name} = [new {$className}(\$data[self::{$const}])];
                } else {
                    \${$name} = [];
                    foreach(\$data[self::{$const}] as \$i => \$v) {
                        if (null === \$v) {
                            continue;
                        }
                        if (is_object(\$v)) {
                            if (!(\$v instanceof {$className})) {
                                throw new \InvalidArgumentException(sprintf(
                                    'Field "{$name}" offset "%d" expected to be instance of "%s" but "%s" found',
                                    \$i,
                                    {$className}::class,
                                    get_class(\$v)
                                ));
                            }
                            \${$name}[] = \$v;
                        } elseif (!is_array(\$v)) {
                            throw new \InvalidArgumentException(sprintf(
                                'Field "{$name}" offset "%d" expected to be associative array, saw "%s"',
                                \$i,
                                gettype(\$v)
                            ));
                        } elseif ([] === \$v) {
                            continue;
                        } else {
                            \${$name}[] = new {$className}(\$v);
                        }
                    }
                    \$this->{$name} = ([] === \${$name}) ? null : \${$name};
                }
            }
        }

EOT;
            } else {
                $c .= <<<EOT
        if (isset(\$data[self::{$const}])) {
            if (is_object(\$data[self::{$const}])) {
                if (!(\$data[self::{$const}] instanceof {$className})) {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" must be instance of "%s" or array descriptor, saw instance of "%s".',
                        {$className}::class,
                        get_class(\$data[self::{$const}])
                    ));
                }
                \$this->{$name} = \$data[self::{$const}];
            } elseif (is_array(\$data[self::{$const}])) {
                if (!is_string(key(\$data[self::{$const}]))) {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" must be instance of "%s" or array descriptor, numerically-indexed array.',
                        {$className}::class
                    ));
                }
                \$this->{$name} = new {$className}(\$data[self::{$const}]);
            } else {
                if (!(\$data[self::{$const}] instanceof {$className})) {
                    throw new \\InvalidArgumentException(sprintf(
                        'Field "{$name}" must be instance of "%s" or array descriptor, saw "%s".',
                        {$className}::class,
                        gettype(\$data[self::{$const}])
                    ));
                }
            }
        }

EOT;
            }
        } elseif ($property->isCollection()) {
            $c .= <<<EOT
        if (isset(\$data[self::{$const}]) && is_array(\$data[self::{$const}])) {
            \${$name} = [];
            foreach(\$data[self::{$const}] as \$i => \$v) {
                if (null === \$v) {
                    continue;
                }
                if (!is_scalar(\$v)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Field "{$name}" offset "%d" expected to be scalar value, saw "%s"',
                        \$i,
                        gettype(\$v)
                    ));
                }
                \${$name}[] = \$v;
            }
            \$this->{$name} = ([] === \${$name}) ? null : \${$name};
        }

EOT;

        } else {
            $c .= <<<EOT
        if (isset(\$data[self::{$const}])) {
            if (is_scalar(\$data[self::{$const}])) {
                \$this->{$name} = \$data[self::{$const}];
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Field "{$name}" expected to be scalar value, saw "%s"',
                    gettype(\$data[self::{$const}])
                ));
            }            
        }

EOT;
        }
    }

    if ($api) {
        if ($api->isPageable()) {
            $c .= <<<PHP
        \$this->requestPage = \$requestPage;
        \$this->requestPageSize = \$requestPageSize;
        \$this->totalReturnCount = \$totalReturnCount;

PHP;
        } elseif ($api->isList()) {
            $c .= <<<PHP
        \$this->totalReturnCount = \$totalReturnCount;

PHP;
        }
    }

    return $c . "    }";
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\Variable[] $optionalArgs
 * @return string
 */
function buildOptionalArgumentCodeDocBloc(array $optionalArgs): string
{
    $nameBuffer = 0;
    $typeBuffer = 0;
    foreach ($optionalArgs as $arg) {
        if (($n = strlen($arg->getName())) > $nameBuffer) {
            $nameBuffer = $n;
        }
        if (($n = strlen($arg->getPHPTypeTagValue())) > $typeBuffer) {
            $typeBuffer = $n;
        }
    }

    // account for ""
    $nameBuffer += 2;
    // account for {}
    $typeBuffer += 2;

    $bloc = <<<EOT
     * <code>
     *      \$optArgs = [

EOT;

    foreach ($optionalArgs as $arg) {
        $bloc .= sprintf(
            "     *          %-{$nameBuffer}s => %-{$typeBuffer}s // %s\n",
            "'{$arg->getName()}'",
            "{{$arg->getPHPTypeTagValue()}}",
            ucfirst($arg->getDescription())
        );
    }

    return $bloc . <<<EOT
     *      ];
     * </code>

EOT;
}

/**
 * @param \MyENA\CloudStackClientGenerator\API\VariableContainer $fields
 * @return string
 */
function buildClassFieldConstants(VariableContainer $fields): string
{
    $buffer = 0;
    foreach ($fields as $field) {
        if (($n = strlen($field->getFieldConstantName(false))) > $buffer) {
            $buffer = $n;
        }
    }

    $out = '';
    foreach ($fields as $field) {
        $out .= sprintf(
            "    const %-{$buffer}s = '%s';\n",
            $field->getFieldConstantName(false),
            $field->getName()
        );
    }
    return $out;
}