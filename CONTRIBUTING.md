# Contributing

Thank you for helping improve KingdomHub.

## Workflow

1. Create an issue for substantial changes.
2. Keep pull requests focused.
3. Add tests for new behavior.
4. Run `php artisan test`, `./vendor/bin/pint --test`, and `npm run build`.
5. Document new modules in `README.md` when the extension pattern changes.

## Coding Standards

- Follow Laravel conventions and PSR-12.
- Keep controllers thin.
- Put business logic in services.
- Use named routes.
- Keep Blade templates presentational.
- Avoid unnecessary dependencies.
