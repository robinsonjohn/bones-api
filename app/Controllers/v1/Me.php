<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Controllers\v1;

use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\RBAC\Exceptions\InvalidUserException;

class Me extends Users
{

    /**
     * @param $params
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ChannelNotFoundException
     * @throws InvalidUserException
     */

    public function me($params): void
    {

        if (!isset($params['resource'])) {

            $this->index([
                'id' => $this->user_id
            ]);

        } else {

            switch ($params['resource']) {

                case 'permissions':

                    $this->_getUserPermissions($this->user_id);
                    break;

                case 'roles':

                    $this->_getUserRoles($this->user_id);
                    break;

                case 'groups':

                    $this->_getUserGroups($this->user_id);
                    break;

                default:

                    abort(404);

            }

        }

    }

}