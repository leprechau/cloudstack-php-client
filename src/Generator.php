<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator;

use MyENA\CloudStackClientGenerator\API\ObjectVariable;
use MyENA\CloudStackClientGenerator\API\Variable;
use MyENA\CloudStackClientGenerator\Configuration\Environment;
use Psr\Log\LoggerInterface;

/**
 * Class Generator
 *
 * @package MyENA\CloudStackClientGenerator
 */
class Generator
{
    private const TEMPLATE_DIR     = __DIR__ . '/../templates';
    private const COMMAND_MAP_FILE = __DIR__ . '/../files/command_event_map.php';

    /** @var \MyENA\CloudStackClientGenerator\Configuration */
    protected $config;
    /** @var \MyENA\CloudStackClientGenerator\Configuration\Environment */
    protected $env;

    /** @var \Psr\Log\LoggerInterface */
    protected $log;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var API[] */
    protected $apis = [];

    /** @var \MyENA\CloudStackClientGenerator\API\ObjectVariable[] */
    protected $sharedObjectMap = [];

    /** @var string[] */
    protected $responseMap = [];

    /** @var string */
    protected $srcDir;
    /** @var string */
    protected $responseDir;
    /** @var string */
    protected $responseTypesDir;
    /** @var string */
    protected $requestDir;

    /** @var array */
    protected $commandEventMap;

    /**
     * Generator constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \MyENA\CloudStackClientGenerator\Configuration $config
     * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $environment
     * @throws \Exception
     */
    public function __construct(LoggerInterface $logger, Configuration $config, Environment $environment)
    {
        $this->log = $logger;

        $this->config = $config;
        $this->env = $environment;

        $this->log->info('Generator constructing with environment "' . $environment->getName() . '"');

        $this->log->debug('Loading command map from ' . self::COMMAND_MAP_FILE);
        $this->commandEventMap = require self::COMMAND_MAP_FILE;

        $this->log->debug('Loading Twig with template dir ' . self::TEMPLATE_DIR);
        $twigLoader = new \Twig_Loader_Filesystem(self::TEMPLATE_DIR, true);
        $this->twig = new \Twig_Environment(
            $twigLoader,
            ['debug' => true, 'strict_variables' => true, 'autoescape' => false]
        );

        $this->log->debug('Registering Twig extensions...');
        $this->registerTwigExtensions();
        $this->log->debug('Registering Twig filters...');
        $this->registerTwigFilters();
        $this->log->debug('Registering Twig functions...');
        $this->registerTwigFunctions();

        if (null !== $environment->getComposer()) {
            $this->srcDir = sprintf('%s/src', $this->env->getOut());
        } else {
            $this->srcDir = $this->env->getOut();
        }
        $this->responseDir = sprintf('%s/CloudStackResponse', $this->srcDir);
        $this->responseTypesDir = sprintf('%s/Types', $this->responseDir);
        $this->requestDir = sprintf('%s/CloudStackRequest', $this->srcDir);
    }

    /**
     * Execute generation of CloudStack API client
     *
     * @throws \Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function generate(): void
    {
        $this->log->info('Initializing directories...');
        $this->initializeDirectories();

        $source = $this->env->getSourceProvider();
        if (!$source) {
            throw new \LogicException(sprintf(
                'Unable to determine Source provider for env %s',
                $this->env->getName()
            ));
        }
        $this->log->info("Compiling APIs from source \"{$source->getName()}\"...");
        $apis = $source->getApis();
        $capabilities = $source->getCapabilities();

        $this->log->info("CloudStack Version: {$capabilities->cloudstackversion}");
        if (null !== $this->env->getComposer()) {
            $this->env->getComposer()->setCloudStackVersion($capabilities->cloudstackversion);

            $this->log->info('Validating composer.json...');
            $this->env->getComposer()->validate();
        }

        $this->compileAPIs($apis);
        ksort($this->apis, SORT_NATURAL);

        $this->log->info(count($this->apis) . ' API(s) found.');

        $this->log->debug('Setting Twig globals');
        $this->twig->addGlobal('config', $this->config);
        $this->twig->addGlobal('env', $this->env);
        $this->twig->addGlobal('log', $this->log);
        $this->twig->addGlobal('capabilities', $capabilities);
        $this->twig->addGlobal('overloadedClasses', $this->config->getOverloadedClasses());

        $this->log->info('Writing static templates...');
        $this->writeOutStaticTemplates();
        $this->log->info('Writing Client class...');
        $this->writeOutClient();

        $this->log->info('Writing Request Models...');
        $this->writeOutRequestModels();
        $this->log->info('Writing Shared Response Models...');
        $this->writeOutSharedResponseModels();
        $this->log->info('Writing Response Models...');
        $this->writeOutResponseModels();
    }

    /**
     * Attempts to clean up output directories
     */
    protected function initializeDirectories(): void
    {
        if (!is_dir($this->srcDir) && !mkdir($this->srcDir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s"', $this->srcDir));
        }
        if (!is_dir($this->responseDir) && !mkdir($this->responseDir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s"', $this->responseDir));
        }
        if (!is_dir($this->responseTypesDir) && !mkdir($this->responseTypesDir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s"', $this->responseTypesDir));
        }
        if (!is_dir($this->requestDir) && !mkdir($this->requestDir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s"', $this->requestDir));
        }
        $this->cleanDirectory($this->srcDir);
        $this->cleanDirectory($this->responseDir);
        $this->cleanDirectory($this->responseTypesDir);
        $this->cleanDirectory($this->requestDir);
    }

    /**
     * @param string $dir
     */
    protected function cleanDirectory(string $dir): void
    {
        if (0 < ($cnt = count(($phpFiles = glob($dir . '/*.php'))))) {
            $this->log->info(sprintf('Directory "%s" has "%d" php file%s, emptying...', $dir, $cnt,
                (1 === $cnt ? '' : 's')));
            foreach ($phpFiles as $phpFile) {
                if (!@unlink($phpFile)) {
                    $this->log->warning(sprintf('Unable to delete file "%s"', $phpFile));
                }
            }
        } else {
            $this->log->debug(sprintf('Directory "%s" is already empty', $dir));
        }
    }

    /**
     * @param array $apis
     */
    protected function compileAPIs(array $apis): void
    {
        foreach ($apis as $apiDef) {
            $api = new API();

            $api->setName(trim($apiDef->name));
            $api->setDescription(trim($apiDef->description));
            $api->setAsync((bool)$apiDef->isasync);
            if (isset($apiDef->since)) {
                $api->setSince($apiDef->since);
            }
            if (isset($apiDef->related)) {
                $api->setRelatedString($apiDef->related);
            }

            $this->parseParameters($api, $apiDef->params);
            $this->parseResponse($api, $apiDef->response);

            $api->getParameters()->nameSort();

            if ($api->isAsync()) {
                if (isset($this->commandEventMap[$api->getName()])) {
                    $api->setEventType($this->commandEventMap[$api->getName()]);
                } else {
                    $this->log->warning(sprintf('No async event present in map for %s', $api->getName()));
                }
            }

            $this->apis[$api->getName()] = $api;
        }
    }

    /**
     * @param \MyENA\CloudStackClientGenerator\API $api
     * @param array $params
     */
    protected function parseParameters(API $api, array $params): void
    {
        foreach ($params as $param) {
            // blank objects, why do you exist?
            if (!isset($param->name)) {
                continue;
            }

            if (null !== ($var = $this->buildVariable(false, $param))) {
                $api->getParameters()->add($var);
            }
        }
    }

    /**
     * @param bool $inResponse
     * @param \stdClass $def
     * @return \MyENA\CloudStackClientGenerator\API\Variable|null
     */
    protected function buildVariable(bool $inResponse, \stdClass $def): ?Variable
    {
        if (!isset($def->name)) {
            return null;
        }

        $var = new Variable($inResponse, trim($def->name));
        $var->setType($def->type);

        if (isset($def->description)) {
            $var->setDescription(trim($def->description));
        }
        if (isset($def->required)) {
            $var->setRequired((bool)$def->required);
        }
        if (isset($def->length)) {
            $var->setLength((int)$def->length);
        }
        if (isset($def->since)) {
            $var->setSince($def->since);
        }
        if (isset($def->related)) {
            $var->setRelatedString($def->related);
        }

        if ('' === $var->getDescription()) {
            switch ($var->getName()) {
                case 'pagesize':
                    $var->setDescription('the number of entries per page');
                    break;
                case 'page':
                    $var->setDescription('the page number of the result set');
                    break;
            }
        }

        return $var;
    }

    /**
     * @param \MyENA\CloudStackClientGenerator\API $api
     * @param array $response
     */
    protected function parseResponse(API $api, array $response): void
    {
        $obj = new ObjectVariable(true, $api->getName(), $this->env->getNamespace(), false);
        $obj->setOverloadedClass($this->config->getOverloadedClasses()->getOverloadedClass($obj->getClassName()));
        $obj->setDescription($api->getDescription());
        $obj->setSince($api->getSince());
        $obj->setRelated($api->getRelated());

        foreach ($response as $prop) {
            if (isset($prop->response)) {
                $var = $this->getSharedObject($prop);
            } else {
                $var = $this->buildVariable(true, $prop);
            }

            if (null === $var) {
                continue;
            }

            $obj->getProperties()->add($var);
        }

        $obj->getProperties()->nameSort();

        $api->setResponse($obj);
    }

    /**
     * @param \stdClass $def
     * @return \MyENA\CloudStackClientGenerator\API\ObjectVariable
     */
    protected function getSharedObject(\stdClass $def): ObjectVariable
    {
        $name = trim($def->name);

        if (isset($this->sharedObjectMap[$name])) {
            $this->parseObjectProperties($this->sharedObjectMap[$name], $def->response);
            return $this->sharedObjectMap[$name];
        }
        $obj = new ObjectVariable(true, $name, $this->env->getNamespace(), true);
        $obj->setOverloadedClass($this->config->getOverloadedClasses()->getOverloadedClass($obj->getClassName()));
        $obj->setType($def->type);
        $obj->setDescription($def->type);

        $this->parseObjectProperties($obj, $def->response);

        $this->sharedObjectMap[$obj->getName()] = $obj;

        return $obj;
    }

    /**
     * @param \MyENA\CloudStackClientGenerator\API\ObjectVariable $object
     * @param array $defs
     */
    protected function parseObjectProperties(ObjectVariable $object, array $defs): void
    {
        $properties = $object->getProperties();

        foreach ($defs as $def) {
            $name = trim($def->name);

            if (null === $properties->get($name)) {
                $var = $this->buildVariable($object->inResponse(), $def);
                if (null === $var) {
                    continue;
                }

                $properties->add($var);
            }
        }
    }

    /**
     * @param string $file
     * @param string $data
     * @return bool|int
     */
    protected function writeFile(string $file, string $data)
    {
        $this->log->debug('Writing ' . mb_strlen($data) . ' bytes to ' . $file);
        return file_put_contents($file, $data);
    }

    /**
     * @param string $sourcePrefix
     * @param string $outputPrefix
     * @param array $additionalTwigArgs
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeFromDirectory(
        string $outputPrefix,
        string $sourcePrefix,
        array $additionalTwigArgs = []
    ): void {
        foreach (new \DirectoryIterator(self::TEMPLATE_DIR.'/'.$sourcePrefix) as $template) {
            if ($template->isFile() && !$template->isDot() && 'twig' === $template->getExtension()) {
                $classFile = $template->getBasename('.twig');
                $this->writeFile(
                    "{$outputPrefix}/{$classFile}",
                    $this->twig
                        ->load("{$sourcePrefix}/{$template->getFilename()}")
                        ->render($additionalTwigArgs)
                );
            }
        }
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeOutStaticTemplates()
    {
        $this->writeFile(
            $this->env->getOut() . '/LICENSE',
            file_get_contents(__DIR__ . '/../LICENSE')
        );

        if (null !== $this->env->getComposer()) {
            $this->writeFile(
                $this->env->getOut() . '/composer.json',
                $this->twig->load('composer.json.twig')->render([])
            );
        }

        $this->writeFile(
            $this->srcDir . '/CloudStackClientConfiguration.php',
            $this->twig->load('configuration.php.twig')->render([])
        );

        $this->writeFile(
            $this->srcDir . '/CloudStackEventTypes.php',
            $this->twig->load('eventTypes.php.twig')->render([])
        );

        $this->writeFile(
            $this->responseDir . '/AsyncJobStartResponse.php',
            $this->twig->load('responses/asyncJobStart.php.twig')->render([])
        );

        $this->writeFile(
            $this->responseDir . '/AccessVmConsoleProxyResponse.php',
            $this->twig->load('responses/accessVmConsoleProxy.php.twig')->render([])
        );

        $this->writeFile(
            $this->responseTypesDir . '/DateType.php',
            $this->twig->load('responses/dateType.php.twig')->render([])
        );

        $this->writeFile(
            $this->srcDir . '/CloudStackHelpers.php',
            $this->twig->load('helpers.php.twig')->render([])
        );

        // request interfaces
        $this->writeFromDirectory($this->requestDir, 'requests/interfaces');

        // response interfaces
        $this->writeFromDirectory($this->responseDir, 'responses/interfaces');

        $this->writeFile(
            $this->requestDir . '/AccessVmConsoleProxyRequest.php',
            $this->twig->load('requests/accessVmConsoleProxy.php.twig')->render([])
        );

        // exception classes
        $this->writeFromDirectory($this->srcDir, 'exceptions');

        $this->writeFile(
            $this->srcDir . '/CloudStackGenerationMeta.php',
            $this->twig->load('meta.php.twig')->render([])
        );
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeOutClient()
    {
        $this->writeFile(
            $this->srcDir . '/CloudStackClient.php',
            $this->twig->load('client/class.php.twig')->render(['apis' => $this->apis])
        );
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeOutRequestModels()
    {
        $template = $this->twig->load('models/request.php.twig');

        foreach ($this->apis as $api) {
            $this->log->info(sprintf(
                'Writing request class for API %s...',
                $api->getName()
            ));
            $className = $api->getRequestClassName();
            $this->writeFile(
                $this->requestDir . '/' . $className . '.php',
                $template->render(['api' => $api])
            );
        }
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeOutSharedResponseModels()
    {
        $template = $this->twig->load('models/response.php.twig');

        foreach ($this->sharedObjectMap as $name => $class) {
            $class->getProperties()->nameSort();
            $className = $class->getClassName();
            $this->writeFile(
                $this->responseDir . '/' . $className . '.php',
                $template->render(['obj' => $class])
            );
        }
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function writeOutResponseModels()
    {
        $template = $this->twig->load('models/response.php.twig');

        foreach ($this->apis as $name => $api) {
            $response = $api->getResponse();
            $className = $response->getClassName();

            $this->writeFile(
                $this->responseDir . '/' . $className . '.php',
                $template->render(['api' => $api, 'obj' => $response])
            );
        }
    }

    /**
     * Registers any / all extensions needed for Twig generation
     */
    protected function registerTwigExtensions(): void
    {
        $this->twig->addExtension(new \Twig_Extensions_Extension_Text());
    }

    /**
     * Registers a few filters for use in Twig templates
     */
    protected function registerTwigFilters(): void
    {
        $this->twig->addFilter(new \Twig_Filter(
            'ucfirst',
            function ($in) {
                return ucfirst($in);
            },
            ['is_safe' => ['html']]
        ));
    }

    /**
     * Registers a few functions for use in Twig templates
     *
     * @throws \Exception
     */
    protected function registerTwigFunctions()
    {
        $map = $this->commandEventMap;
        $this->twig->addFunction(new \Twig_Function(
            'command_event',
            function (string $in) use ($map): string {
                return $map[$in] ?? '';
            },
            ['is_safe' => ['html']]
        ));

        $rootNS = $this->env->getNamespace();
        $this->twig->addFunction(new \Twig_function(
            'namespace_stmt',
            function (string $in = '') use ($rootNS): string {
                if ('' === $rootNS) {
                    if ('' === $in) {
                        return '';
                    }
                    return "namespace {$in};";
                }
                if ('' === $in) {
                    return "namespace {$rootNS};";
                }
                return "namespace {$rootNS}\\{$in};";
            },
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new \Twig_Function(
            'namespace_path',
            function (string $in = '', bool $prefix = false) use ($rootNS): string {
                if ('' === $rootNS) {
                    if ('' === $in) {
                        return '';
                    }
                    return $prefix ? "\\{$in}" : $in;
                }
                if ('' === $in) {
                    return $prefix ? "\\{$rootNS}" : $rootNS;
                }
                return $prefix ? "\\{$rootNS}\\{$in}" : "{$rootNS}\\{$in}";
            },
            ['is_safe' => ['html']]
        ));

        $now = new \DateTime();
        $this->twig->addFunction(new \Twig_Function(
            'now',
            function (string $format = '') use ($now) : string {
                if ('' === $format) {
                    $format = 'Y-m-d';
                }
                return $now->format($format);
            },
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new \Twig_Function(
            'var_export',
            'var_export',
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new \Twig_Function(
            'json_decode',
            'json_decode',
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new \Twig_Function(
            'tag_indent',
            '\\MyENA\\CloudStackClientGenerator\\tagIndent',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'swagger_definition_tag',
            '\\MyENA\\CloudStackClientGenerator\\buildSwaggerDefinitionTag',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'escape_swagger_string',
            '\\MyENA\\CloudStackClientGenerator\\escapeSwaggerString',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'since_tag_line',
            '\\MyENA\\CloudStackClientGenerator\\buildSinceTagLine',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'required_tag_line',
            '\\MyENA\\CloudStackClientGenerator\\buildRequiredTagLine',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'bor',
            function (int ...$ints): int {
                $v = 0x0;
                foreach ($ints as $int) {
                    $v |= $int;
                }
                return $v;
            }
        ));

        $this->twig->addFunction(new \Twig_Function(
            'file_header',
            '\\MyENA\\CloudStackClientGenerator\\buildFileHeader',
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new \Twig_Function(
            'determine_class',
            '\\MyENA\\CloudStackClientGenerator\\determineClass',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'determine_swagger_name',
            '\\MyENA\\CloudStackClientGenerator\\determineSwaggerName',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new \Twig_Function(
            'object_constructor',
            '\\MyENA\\CloudStackClientGenerator\\objectConstructor',
            ['is_safe' => ['html']]
        ));
    }
}