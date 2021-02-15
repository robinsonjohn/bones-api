# Events

The following events have been added:

- `auth.success`
- `jwt.authenticated`
- `group.create`
- `group.update`
- `group.delete`
- `group.users.grant`
- `group.users.revoke`
- `permission.create`
- `permission.update`
- `permission.delete`
- `permission.roles.grant`
- `permission.roles.revoke`
- `role.create`
- `role.update`
- `role.delete`
- `role.permissions.grant`
- `role.permissions.revoke`
- `role.users.grant`
- `role.users.revoke`
- `user.create`
- `user.update`
- `user.delete`
- `user.roles.grant`
- `user.roles.revoke`
- `user.groups.grant`
- `user.groups.revoke`
- `user.meta.create`
- `user.meta.update`
- `user.meta.delete`

Event hooks are managed in the `/resources/events.php` file.

Hooks exist for the following events:

- [app.bootstrap](#appbootstrap)
- [jwt.authenticated](#jwtauthenticated)

## app.bootstrap

### log_context

The requested URL and client IP address is added to each log entry.

## jwt.authenticated

### log_user

The user ID is added to the context of each log entry.

