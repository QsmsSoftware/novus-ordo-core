<?php

namespace App\Utils;

// Based on: https://learn.microsoft.com/en-us/dotnet/api/system.net.httpstatuscode?view=net-9.0
final class HttpStatusCode {
    private function __construct()
    {
        
    }

    /** OK indicates that the request succeeded and that the requested information is in the response. This is the most common status code to receive. */
    public const int OK = 200;
    /** Created indicates that the request resulted in a new resource created before the response was sent. */
    public const int Created = 201;
    /**  BadRequest indicates that the request could not be understood by the server. BadRequest is sent when no other error is applicable, or if the exact error is unknown or does not have its own error code. */
    public const int BadRequest = 400;
    /** Unauthorized indicates that the requested resource requires authentication. The WWW-Authenticate header contains the details of how to perform the authentication. */
    public const int Unauthorized = 401;
    /** NotFound indicates that the requested resource does not exist on the server. */
    public const int NotFound = 404;
    /** Conflict indicates that the request could not be carried out because of a conflict on the server. */
    public const int Conflict = 409;
    /** UnprocessableContent indicates that the request was well-formed but was unable to be followed due to semantic errors. */
    public const int UnprocessableContent = 422;
}