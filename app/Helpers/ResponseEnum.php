<?php

namespace App\Helpers;

class ResponseEnum
{
    // 001 ~ 099 represents system status; 100 ~ 199 represents authorization business; 200 ~ 299 represents user business

    /*-------------------------------------------------------------------------------------------*/
    // 100 series represents information prompts, these statuses indicate temporary responses
    // 100 - Continue
    // 101 - Switching Protocols

    /*-------------------------------------------------------------------------------------------*/
    // 200 indicates server successfully accepted client request
    const HTTP_OK = [200001, 'Operation successful'];
    const HTTP_ERROR = [200002, 'Operation failed'];
    const HTTP_ACTION_COUNT_ERROR = [200302, 'Operation too frequent'];
    const USER_SERVICE_LOGIN_SUCCESS = [200200, 'Login successful'];
    const USER_SERVICE_LOGIN_ERROR = [200201, 'Login failed'];
    const USER_SERVICE_LOGOUT_SUCCESS = [200202, 'Logout successful'];
    const USER_SERVICE_LOGOUT_ERROR = [200203, 'Logout failed'];
    const USER_SERVICE_REGISTER_SUCCESS = [200104, 'Registration successful'];
    const USER_SERVICE_REGISTER_ERROR = [200105, 'Registration failed'];
    const USER_ACCOUNT_REGISTERED = [23001, 'Account already registered'];

    /*-------------------------------------------------------------------------------------------*/
    // 300 series represents server redirection, pointing elsewhere, client browser must take additional actions to implement the request
    // 302 - Object has moved
    // 304 - Not modified
    // 307 - Temporary redirect

    /*-------------------------------------------------------------------------------------------*/
    // 400 series represents client error request errors, cannot get data, or not found, etc.
    // 400 - Bad request
    const CLIENT_NOT_FOUND_HTTP_ERROR = [400001, 'Request failed'];
    const CLIENT_PARAMETER_ERROR = [400200, 'Parameter error'];
    const CLIENT_CREATED_ERROR = [400201, 'Data already exists'];
    const CLIENT_DELETED_ERROR = [400202, 'Data does not exist'];
    // 401 - Access denied
    const CLIENT_HTTP_UNAUTHORIZED = [401001, 'Authorization failed, please login first'];
    const CLIENT_HTTP_UNAUTHORIZED_EXPIRED = [401200, 'Account information expired, please login again'];
    const CLIENT_HTTP_UNAUTHORIZED_BLACKLISTED = [401201, 'Account logged in on other device, please login again'];
    // 403 - Forbidden access
    // 404 - File or directory not found
    const CLIENT_NOT_FOUND_ERROR = [404001, 'Page not found'];
    // 405 - HTTP verb used to access this page is not allowed (method not allowed)
    const CLIENT_METHOD_HTTP_TYPE_ERROR = [405001, 'HTTP request type error'];
    // 406 - Client browser does not accept the MIME type of the requested page
    // 407 - Proxy authentication required
    // 412 - Precondition failed
    // 413 – Request entity too large
    // 414 - Request URI too long
    // 415 – Unsupported media type
    // 416 – The requested range cannot be satisfied
    // 417 – Execution failed
    // 423 – Locked error

    /*-------------------------------------------------------------------------------------------*/
    // 500 series represents server errors, server terminated due to code or other reasons
    // Server operation error codes: 500 ~ 599 prefix, followed by 3 digits
    // 500 - Internal server error
    const SYSTEM_ERROR = [500001, 'Server error'];
    const SYSTEM_UNAVAILABLE = [500002, 'Server is under maintenance, temporarily unavailable'];
    const SYSTEM_CACHE_CONFIG_ERROR = [500003, 'Cache configuration error'];
    const SYSTEM_CACHE_MISSED_ERROR = [500004, 'Cache miss'];
    const SYSTEM_CONFIG_ERROR = [500005, 'System configuration error'];

    // Business operation error codes (external service or internal service calls)
    const SERVICE_REGISTER_ERROR = [500101, 'Registration failed'];
    const SERVICE_LOGIN_ERROR = [500102, 'Login failed'];
    const SERVICE_LOGIN_ACCOUNT_ERROR = [500103, 'Account or password error'];
    const SERVICE_USER_INTEGRAL_ERROR = [500200, 'Insufficient points'];

    //501 - Header value specifies a configuration that is not implemented
    //502 - Web server received an invalid response when used as gateway or proxy server
    //503 - Service unavailable. This error code is specific to IIS 6.0
    //504 - Gateway timeout
    //505 - HTTP version not supported
    /*-------------------------------------------------------------------------------------------*/
}