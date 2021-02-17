# Application documentation

## Resources

- [Bootstrap](resources/bootstrap.md)
- [Cron jobs](resources/cron-jobs.md)
- [Events](resources/events.md)
- [Filters](resources/filters.md)

## Controllers

The `Home` controller is included with this application, but can be removed.

### v1

The `/app/Controllers/v1` directory exists to contain all controllers for `v1` of your API.

**ApiController**

All API controllers should extend this controller.

The following variables are set in the constructor:

- `$this-api` as `Bayfront\Bones\Services\BonesApi` instance
- `$this-auth` as `Bayfront\Bones\Services\BonesAuth\BonesAuth` instance
- `$this-base_uri`- absolute/relative base URI to the API.

The following variables are defined only if the `$requires_authentication`
constructor parameter is `TRUE`:

- `$this->token`- JWT payload as an array
- `$this->user_id` as a string
- `$this->user_groups`- Array of group ID's
- `$this->user_permissions`- Array of all the user's permissions

In addition, this controller includes the following methods:

- `requireValues`
- `hasPermissions`
- `hasAnyPermissions`
- `getGroupedUserIds`
- `userCan`

**Auth**

Endpoints do not require authentication. 
Failed requests are rate limited to the `api.rate_limit_auth` configuration setting.
Successful requests returns an `AuthResource` schema containing the access and refresh tokens.

All requests are logged.

**ExampleApiController**

Endpoints require authentication and are rate limited to the limit set in the JWT.

**Groups**

Endpoints require authentication and are rate limited to the limit set in the JWT.

This controller utilizes the `BonesAuth` service as the data model.

**Me**

Endpoints require authentication and are rate limited to the limit set in the JWT.

This controller utilizes the `BonesAuth` service as the data model.

**Permissions**

Endpoints require authentication and are rate limited to the limit set in the JWT.

This controller utilizes the `BonesAuth` service as the data model.

**PublicController**

Endpoints do not require authentication and are rate limited to the `api.rate_limit_public` configuration setting.
Public endpoints are useful for cases such as webhooks and API status checks.

**Roles**

Endpoints require authentication and are rate limited to the limit set in the JWT.

This controller utilizes the `BonesAuth` service as the data model.

**Users**

Endpoints require authentication and are rate limited to the limit set in the JWT.

This controller utilizes the `BonesAuth` service as the data model.

## Exceptions

There are no custom exceptions included with this application.

## Helpers

There are no custom helpers included with this application.

## Models

There are no models included with this application.

## Services

This application uses the following services, included with the Bones framework:

- [BonesApi](https://github.com/bayfrontmedia/bones/blob/master/_docs/services/bonesapi.md)
- [BonesAuth](https://github.com/bayfrontmedia/bones/blob/master/_docs/services/bonesauth.md)