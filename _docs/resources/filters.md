# Filters

Filters are managed in the `/resources/filters.php` file.

In addition to the [default Bones filters](https://github.com/bayfrontmedia/bones/blob/master/_docs/libraries/hooks.md#default-bones-filters), the following filters are available to be used:

- [router.route_prefix](#routerroute_prefix)
- [jwt.payload](#jwtpayload)

## router.route_prefix

The `router.route_prefix` filter is used in the `/resources/routes.php` file to filter the prefix of all routes.

## jwt.payload

The `jwt.payload` filter is used in the `Auth` controller to filter the payload of a JWT before it is issued.