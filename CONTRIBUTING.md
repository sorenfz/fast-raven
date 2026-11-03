# Contributing

Thank you for your interest in contributing to this project!

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Create a new branch for your feature or bugfix
4. Make your changes
5. Commit with clear, descriptive messages
6. Push to your fork
7. Open a pull request

## Testing

Run the full test suite before pushing:
```bash
composer test
```

## Pull Request Guidelines

- Provide a clear description of the changes.
- Reference any related issues.
- **Code Style**: 
    - Use strict types (`declare(strict_types=1);` not required but Types are).
    - Favor `Bee` helpers over raw PHP functions where applicable.
    - Use Enums (`Types\`) for magic strings.
- **Documentation**: Update `docs/FRAMEWORK.md` if you change public APIs.
- **Tests**: Include unit tests for new functionality.

## Code of Conduct

Please be respectful and constructive in all interactions.

## Questions?

Feel free to open an issue if you have questions or suggestions.
