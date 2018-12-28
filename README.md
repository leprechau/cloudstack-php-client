CloudStack PHP Client
=====================

PHP client library for the CloudStack API v4.8+ ([reference](http://cloudstack.apache.org/api.html))

This project was originally forked from the following projects:
  * [qpleple/cloudstack-client-generator](https://github.com/qpleple/cloudstack-client-generator)
  * [qpleple/cloudstack-php-client](https://github.com/qpleple/cloudstack-php-client)

This project combines these two tools into one project.  The code generation is no longer done via scraping of the HTML
 documentation.  We now use the provided ```listApis``` call in the CloudStack API to generate the libraries.

The code generated is tagged for [phpdoc](https://github.com/phpDocumentor/phpDocumentor2).

## Installation

Simply download the latest `php-cloudstack-generator.phar` from the releases page and put it where you'd like.  Where to
place the phar will depend heavily on your implementation.

## Code Generation

### 1. Define a configuration file:

Please see [files/config_prototype.yml](./files/config_prototype.yml) for an example configuration file

### 2. Generate client

```php
php-cloudstack-generator cs-gen:generate-client --config="your_config_file.yml" --env="environment in config you wish to generate from"
```

The output of this generator has this basic structure:

```
# as Composer package
/ - {configured output root}
  | - src/
    | - CloudStackRequest/
      | - # Every API will have a corresponding CloudStack**Request class present in here.  If you have Swagger documentation, add this directory to your project's scan path
    | - CloudStackResponse/
      | - # Every API will have a corresponding CloudStack**Response class present in here.  If you have Swagger documentation, add this directory to your project's scan path
    | - CloudStackClient.php                # This is the actual client
    | - CloudStackClientConfiguration.php   # This is the configuration object that must be provided when constructing a client
    | - CloudStackGenerationMeta.php        # This file contains metadata about the most recent generation, including date, api source, and environment configuration (sans api keys and secrets)
    | - # several exception and helper classes that you will rarely directly construct
  | - composer.json
  | - LICENSE
  
  
# as raw code
/ - {configured output root}
  | - CloudStackRequest/
    | - # Every API will have a corresponding CloudStack**Request class present in here.  If you have Swagger documentation, add this directory to your project's scan path
  | - CloudStackResponse/
    | - # Every API will have a corresponding CloudStack**Response class present in here.  If you have Swagger documentation, add this directory to your project's scan path
  | - CloudStackClient.php                # This is the actual client
  | - CloudStackClientConfiguration.php   # This is the configuration object that must be provided when constructing a client
  | - CloudStackGenerationMeta.php        # This file contains metadata about the most recent generation, including date, api source, and environment configuration (sans api keys and secrets)
  | - # several exception and helper classes that you will rarely directly construct
```

### 3. Include in Project

How you include the generated code depends on how you chose to generate the code, either as a Composer library or as 
standalone classes.

If you generated the client as a composer package that has been pushed somewhere Composer can reach, simply add to your
project's composer.json file

If you just generated code, it is recommended you put the code somewhere in the containing projects include or autoload
path(s).

PHP Library Usage
-----------------

### Initialization

```php
    $configuration = new CloudStackClientConfiguration([
        'api_key'      => '',               // YOUR_API_KEY (required)
        'secret_key'   => '',               // YOUR_SECRET_KEY (required)
        'host'         => 'localhost',      // Your CloudStack host (required)
        'scheme'       => 'http',           // http or https (defaults to http)
        'port'         => 8080,             // api port (defaults to 8080)
        'api_path'     => 'client/api',     // admin api path (defaults to 'client/api')
        'console_path' => 'client/console', // console api path (defaults to 'client/console')
        'http_client'  => null,             // GuzzleHttp\ClientInterface compatible client
    ]);
    
    $client = new CloudStackClient($config);
```

### Lists

```php
    $vms = $client->listVirtualMachines();
    foreach ($vms as $vm) {
        printf("%s : %s %s", $vm->id, $vm->name, $vm->state);
    }
```

### Asynchronous tasks

```php
    $job = $client->deployVirtualMachine(1, 259, 1);
    printf("VM being deployed. Job id = %s", $job->jobid);

    print "All jobs";

    foreach ($client->listAsyncJobs() as $job) {
        printf("%s : %s, status = %s", $job->jobid, $job->cmd, $job->jobstatus);
    }
```

You may also optionally wait for a job to finish:
```php
    $result = $client->waitForAsync($job);
```


## Advanced Topics

### API Caching
The code generated by this library has the ability to take advantage of any cache library implementing the 
[Doctrine Cache](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/caching.html)
[interface](https://github.com/doctrine/cache/blob/master/lib/Doctrine/Common/Cache/Cache.php)

Any / all "read" requests (get*, list*, etc.) are allowed to have their responses cached.

The workflow looks like this:

```php
# The below is pseudo-code

// First, determine if this command is cacheable, caching has been configured, and we have a cache provider defined
$cacheable = $cacheableCommand && $cacheConfigured && $cacheProviderProvided;

// determine if above == true
if ($cacheable) {
    // build cache key for this specific request
    // Each key contains the following to prevent any collisions:
    //  1. CloudStack Host from client config
    //  2. API Key from client config
    //  3. API Secret from client config
    //  4. Name of command being executed (listTemplates, for example)
    //  5. The parameters specified for this request are serialized and appended in "{$k}{$v}" fashion
    // The above is prepended with your configure cache prefix after being run through sha1()
    $key = '{configured-prefix}-'.sha1("{cloudStackHost}{apiKey}{secretKey}{commandName}{requestParameterKVPairs}";
    
    // test to see if the key exists in the cache and can be fetched successfully
    if ($cacheProvider->contains($key) && ($fetched = $cacheProvider->fetch($key)) && (false !== $fetched)) {
        // if we got got a cache key, return the cached response
        return $fetched;
    }
}
    
// if we make it here, we're either dealing with a write request, a command for which caching has been disabled, 
// caching has not been configured at all, or a cached response was not found

// ... perform typical API request against cloudstack ...

// once again test cacheability
if ($cacheable) {
    // attempt to store cached response with configured TTL using $key generated above
}

// return response from CloudStack
```

### Response Object Overloading
During code generation, it is possible to specify a custom class that extends a response class that is generated by this
library.

For example, the `tags` concept can be used to store all kinds of implementation-specific business logic with 
virtual machines, template, isos, zones (in the form of `resourcedetails`), the list goes on.  It can be quite tedious
to have to extract values from the tag array every time they need to be reference, so one of the ways in which we 
utilize class overloading is to define our own `ListVirtualMachinesResponse` class with some helper methods to parse and
extract specific tags from the `tags` array.

The only real requirement is that the overloading class MUST retain the same `__construct` args as the class it is 
overloading.  And, of course, it must be loadable by your application.

The generator DOES NOT do any kind of validation on the provided overload list!  If you specify a class that does not
exist, then that query will not work.
