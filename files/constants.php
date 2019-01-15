<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator;

// using this will disable caching entirely for this request
const RESPONSE_CACHE_DISABLE = 0x0;

// this flag will allow an existing cached response to be sought and used, if found
const RESPONSE_CACHE_FETCH = 0x1;

// this flag will allow, in the event that fetching was disabled or an existing cache was not found, the response from
// the given request to be persisted into the cache for later use
const RESPONSE_CACHE_PERSIST = 0x2;

// this will enable both fetching and persisting of cached responses for the request
const RESPONSE_CACHE_ENABLE = RESPONSE_CACHE_FETCH | RESPONSE_CACHE_PERSIST;