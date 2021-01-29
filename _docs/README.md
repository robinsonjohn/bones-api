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

**Auth**

**NOTE:** This controller must be updated to interact with a user model.

Endpoints do not require authentication. 
Failed requests are rate limited to the `api.rate_limit_auth` configuration setting.
Successful requests returns an `AuthResource` schema containing the access and refresh tokens.

All requests are logged.

**ExampleApiController**

Endpoints require authentication and are rate limited to the limit set in the JWT.

**PublicController**

Endpoints do not require authentication and are rate limited to the `api.rate_limit_public` configuration setting.
Public endpoints are useful for cases such as webhooks and API status checks.

## Exceptions

There are no custom exceptions included with this application.

## Helpers

There are no custom helpers included with this application.

## Models

There are no models included with this application.

## Services

This application uses the [BonesApi service](https://github.com/bayfrontmedia/bones/blob/master/_docs/services/bonesapi.md) included with the Bones framework.