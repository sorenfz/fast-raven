# Fast Raven

<img src="docs/logo.png" align="right" width="180" alt="Fast Raven Logo">

![Version](https://img.shields.io/badge/version-v1.1.0-blue.svg) ![Status](https://img.shields.io/badge/status-production--ready-success.svg) ![PHP](https://img.shields.io/badge/php-%5E8.5-777BB4.svg) ![License](https://img.shields.io/badge/license-MIT-blue.svg)

*No bloat. No magic. Just speed.*

**Fast Raven** is a **high-performance PHP** framework for building **fast, monolithic applications**. Optimized for **subdomain architectures**, it processes requests in **1ms** (average speed in shared hosting) while allowing fast-paced and easy development.

<br clear="all">

## Installation

### Requirements
- **PHP** ^8.5
- **MySQL** ^8.0
- **Composer** ^2.9.2
- **Apache** ^2.4 (or Nginx/IIS with appropriate config)

> **Pro Tip**: Enable `php-apcu` and configure `FastCGI` / `PHP-FPM` to reach peak performance.

### Create Project
```bash
composer create-project fast-raven/project app
cd app
./init.sh mysite.local
```

---

## Documentation

- **[Documentation](docs/FRAMEWORK.md)**: Deep dive into the API, Architecture, and Usage.
- **[Roadmap](docs/ROADMAP.md)**: Future plans and versioning strategy.
- **[Contributing](CONTRIBUTING.md)**: Guidelines for contributing.

## License

Fast Raven is open-sourced software licensed under the **[MIT license](LICENSE)**.

**Author**: Arkhyst
