<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\Schemas\AuthResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
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
use Bayfront\RBAC\Exceptions\AuthenticationException;
use Bayfront\RBAC\Exceptions\InvalidMetaException;
use Bayfront\RBAC\Exceptions\InvalidUserException;
use Exception;

/**
 * This controller allows rate limited public access to the authentication endpoints.
 */
class Auth extends ApiController
{

    /**
     * Auth constructor.
     *
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {

        parent::__construct(false); // ApiController

        // Check rate limit

        $this->api->enforceRateLimit('auth-' . Request::getIp(), get_config('api.rate_limit_auth', 5));

    }

    /**
     * Create JWT and return as AuthResource schema.
     *
     * @param array $data
     * @param string $refresh_token
     * @param int $rate_limit (Rate limit per minute)
     *
     * @return void
     *
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws Exception
     */

    protected function _returnJwt(array $data, string $refresh_token, int $rate_limit = 50): void
    {

        // Define and filter JWT payload

        $payload = do_filter('jwt.payload', [
            'user_id' => $data['user_id'],
            'groups' => $data['groups'],
            'rate_limit' => $rate_limit
        ]);

        // auth.success event

        do_event('auth.success', $data['user_id']);

        // Reset rate limit

        $this->api->resetRateLimit('auth-' . Request::getIp(), get_config('api.rate_limit_auth', 5));

        // Create JWT

        $jwt = new Jwt(get_config('app.key'));

        $time = time();

        $token = $jwt
            ->iss(Request::getRequest('protocol') . Request::getRequest('host')) // Issuer
            ->sub($data['login'])
            ->iat($time)
            ->nbf($time)
            ->exp($time + get_config('api.access_token_lifetime'))
            ->encode($payload);

        // Build schema

        $schema = AuthResource::create([
            'accessToken' => $token,
            'refreshToken' => $refresh_token,
            'expiresIn' => get_config('api.access_token_lifetime')
        ]);

        // Respond

        $this->response->setStatusCode(200)->setHeaders([
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate'
        ])->sendJson($schema);

    }

    /**
     * Login using username/password.
     *
     * Creates token and returns AuthResource schema.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    public function login(): void
    {

        // Endpoint requirements

        $this->api->allowedMethods('POST');

        $body = $this->api->getBody([ // Required keys
            'login',
            'password'
        ]);

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'login',
            'password'
        ]))) {

            log_notice('Unsuccessful login: invalid members');

            abort(400, 'Invalid members');

            die;

        }

        // Attempt login

        try {

            $user = $this->auth->authenticate($body['login'], $body['password']);

        } catch (AuthenticationException $e) {

            log_notice('Unsuccessful login: invalid credentials', [
                'login' => $body['login']
            ]);

            abort(401, 'Invalid credentials');

            die;

        }

        if (!$user['enabled']) {

            log_notice('Unsuccessful login: user disabled', [
                'user_id' => $user['id']
            ]);

            abort(403, 'User disabled');

            die;

        }

        // Successful login

        // Create and store refresh token

        $refresh_token = create_key();

        $this->auth->setUserMeta($user['id'], [
            '_refresh_token' => json_encode([
                'token' => $refresh_token,
                'created_at' => time()
            ])
        ]);

        $data = [
            'user_id' => $user['id'],
            'login' => $user['login'],
            'groups' => Arr::pluck($this->auth->getUserGroups($user['id']), 'id')
        ];

        log_info('Successful login', [
            'user_id' => $user['id']
        ]);

        $this->_returnJwt($data, $refresh_token, get_config('api.rate_limit', 50));

    }

    /**
     * Login using access/refresh tokens.
     *
     * Creates token and returns AuthResource schema.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    public function refresh(): void
    {

        // Endpoint requirements

        $this->api->allowedMethods('POST');

        $body = $this->api->getBody([ // Required keys
            'access_token',
            'refresh_token'
        ]);

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'access_token',
            'refresh_token'
        ]))) {

            log_notice('Unsuccessful login refresh: invalid members');

            abort(400, 'Invalid members');

            die;

        }

        // Validate access token

        $jwt = new Jwt(get_config('app.key'));

        try {

            /*
             * Validate the JWT has not been modified, even if it is expired.
             * All that is needed is the user ID
             */

            $token = $jwt->validateSignature($body['access_token'])->decode($body['access_token'], false);

        } catch (TokenException $e) { // Invalid JWT

            log_notice('Unsuccessful login refresh: invalid access token', [
                'access_token' => $body['access_token']
            ]);

            abort(401, 'Invalid access token');

            die;

        }

        // Attempt to fetch refresh token

        try {

            $refresh_token = $this->auth->getUserMeta($token['payload']['user_id'], '_refresh_token');

        } catch (InvalidMetaException $e) {

            log_notice('Unsuccessful login refresh: invalid refresh token', [
                'user_id' => $token['payload']['user_id']
            ]);

            abort(401, 'Invalid refresh token');

            die;

        }

        // Validate refresh token format

        $refresh_token = json_decode($refresh_token, true);

        if (Arr::isMissing($refresh_token, [
            'token',
            'created_at'
        ])) {

            // Delete invalid token

            $this->auth->deleteUserMeta($token['payload']['user_id'], [
                '_refresh_token'
            ]);

            log_notice('Unsuccessful login refresh: invalid refresh token format', [
                'user_id' => $token['payload']['user_id'],
                'refresh_token' => $body['refresh_token']
            ]);

            abort(401, 'Invalid refresh token format');

            die;

        }

        // Validate refresh token value and time

        if ($refresh_token['token'] == $body['refresh_token']) {

            if ($refresh_token['created_at'] > time() - get_config('api.refresh_token_lifetime')) {

                try {

                    $user = $this->auth->getUser($token['payload']['user_id']); // User must exist because meta already fetched

                } catch (InvalidUserException $e) {

                    log_notice('Unsuccessful login: user does not exist', [
                        'user_id' => $token['payload']['user_id']
                    ]);

                    abort(401, 'User does not exist');

                    die;

                }

                if (!$user['enabled']) {

                    log_notice('Unsuccessful login refresh: user disabled', [
                        'user_id' => $user['id']
                    ]);

                    abort(403, 'User disabled');

                    die;

                }

                // Successful login

                // Create and store a new refresh token

                $refresh_token = create_key();

                $this->auth->setUserMeta($user['id'], [
                    '_refresh_token' => json_encode([
                        'token' => $refresh_token,
                        'created_at' => time()
                    ])
                ]);

                $data = [
                    'user_id' => $user['id'],
                    'login' => $user['login'],
                    'groups' => Arr::pluck($this->auth->getUserGroups($user['id']), 'id')
                ];

                log_info('Successful login via refresh token', [
                    'user_id' => $user['id']
                ]);

                $this->_returnJwt($data, $refresh_token, get_config('api.rate_limit', 50));

                return;

            }

            // Delete invalid token

            $this->auth->deleteUserMeta($token['payload']['user_id'], [
                '_refresh_token'
            ]);

            log_notice('Unsuccessful login refresh: expired refresh token', [
                'user_id' => $token['payload']['user_id']
            ]);

            abort(401, 'Expired refresh token');

            die;

        }

        log_notice('Unsuccessful login refresh: invalid credentials', [
            'user_id' => $token['payload']['user_id']
        ]);

        abort(401, 'Invalid credentials');

        die;

    }

}