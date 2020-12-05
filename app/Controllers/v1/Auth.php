<?php

namespace App\Controllers\v1;

use App\Schemas\AuthResource;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\JWT\Jwt;
use Bayfront\JWT\TokenException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Exception;

/**
 * This controller allows rate limited public access to the authentication endpoints.
 */
class Auth extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    /**
     * Auth constructor.
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     */

    public function __construct()
    {

        parent::__construct();

        // Get the Bones API service from the container

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // Check rate limit

        $this->api->enforceRateLimit('auth-' . Request::getIp(), get_config('api.auth_rate_limit', 5));

    }

    /**
     * Create JWT and return as AuthResource schema.
     *
     * @param array $data
     * @param string $refresh_token
     * @param int $rate_limit (Rate limit per minute)
     *
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws Exception
     */

    protected function _returnJwt(array $data, string $refresh_token, int $rate_limit = 50)
    {

        // Reset rate limit

        $this->api->resetRateLimit('auth-' . Request::getIp(), get_config('api.auth_rate_limit', 5));

        // Create JWT

        $jwt = new Jwt(get_config('app.key'));

        $time = time();

        $token = $jwt
            ->iss(Request::getRequest('protocol') . Request::getRequest('host')) // Issuer
            ->sub($data['username'])
            ->iat($time)
            ->nbf($time)
            ->exp($time + get_config('api.access_token_lifetime'))
            ->encode([
                'user_id' => $data['id'],
                'rate_limit' => $rate_limit
            ]);

        // Respond

        $schema = AuthResource::create([
            'access_token' => $token,
            'refresh_token' => $refresh_token,
            'expires_in' => get_config('api.access_token_lifetime')
        ]);

        $this->response->setStatusCode(201)->setHeaders([
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate'
        ])->sendJson($schema);

    }

    /**
     * Login using username/password.
     *
     * Creates token and returns AuthResource schema.
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    public function login()
    {

        // Endpoint requirements

        $this->api->allowedMethods('POST');

        $body = $this->api->getBody([ // Required keys
            'username',
            'password'
        ]);

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'username',
            'password'
        ]))) {

            log_notice('Unsuccessful login: invalid parameters');

            abort(400, 'Request body contains invalid parameters');

        }

        /*
         * ############################################################
         * Start User model
         * ############################################################
         */

        /*
         * Here is where you would interact with the Users model.
         * This should abort with an HTTP status 401 for an authentication error (bad login),
         * or with an HTTP status 403 for an authorization error (not allowed).
         */

        $user = [
            'id' => 1,
            'rate_limit' => get_config('api.rate_limit', 50),
            'username' => $body['username'],
            'password' => $body['password']
        ];

        /*
         * ############################################################
         * End User model
         * ############################################################
         */

        // Successful login. Create and save refresh token

        $refresh_token = create_key();

        /*
         * ############################################################
         * Save the refresh token and issued at time here
         * ############################################################
         */

        log_info('Successful login', [
            'user_id' => $user['id']
        ]);

        $this->_returnJwt($user, $refresh_token, get_config('api.rate_limit', 50));

    }

    /**
     * Login using access/refresh tokens.
     *
     * Creates token and returns AuthResource schema.
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    public function refresh()
    {

        // Endpoint requirements

        $this->api->allowedMethods('POST');

        $body = $this->api->getBody([ // Required keys
            'access_token',
            'refresh_token'
        ]);

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'access_token',
            'refresh_token'
        ]))) {

            log_notice('Unsuccessful login refresh: invalid parameters');

            abort(400, 'Request body contains invalid parameters');

        }

        $jwt = new Jwt(get_config('app.key'));

        try {

            /*
             * Validate the JWT has not been modified, even if it is expired.
             * All that is needed is the user ID
             */

            $token = $jwt->validateSignature($body['access_token'])->decode($body['access_token'], false);

        } catch (TokenException $e) { // Invalid JWT

            log_notice('Unsuccessful login refresh: invalid token', [
                'access_token' => $body['access_token']
            ]);

            abort(401, 'Unable to authenticate: invalid access/refresh token');

            die;

        }

        /*
         * ############################################################
         * Start User model
         * ############################################################
         */

        /*
         * Here is where you would interact with the Users model.
         *
         * For example:
         *
         * 1. Authenticate user using user ID and refresh token.
         *
         * This should abort with an HTTP status 401 for an authentication error
         * (user ID/refresh token mismatch or refresh token is expired),
         * or with an HTTP status 403 for an authorization error (not allowed).
         *
         *      $token['payload']['user_id']
         *      $body['refresh_token']
         *      get_config('api.refresh_token_lifetime')
         *
         *      if ($ISSUED_AT_TIME < time() - $TOKEN_LIFETIME) { // Expired token
         */

        $user = [
            'id' => $token['payload']['user_id'],
            'rate_limit' => get_config('api.rate_limit', 50),
            'username' => 'IN_DATABASE',
            'password' => 'IN_DATABASE',
        ];

        /*
         * ############################################################
         * End User model
         * ############################################################
         */

        // Successful login. Create and save refresh token

        $refresh_token = create_key();

        /*
         * ############################################################
         * Save the refresh token and issued at time here
         * ############################################################
         */

        log_info('Successful login refresh', [
            'user_id' => $user['id']
        ]);

        $this->_returnJwt($user, $refresh_token, get_config('api.rate_limit', 50));

    }

}