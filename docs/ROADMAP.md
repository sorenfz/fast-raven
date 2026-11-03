# Fast Raven Roadmap

This document outlines potential features and improvements for future versions of Fast Raven. These are **not commitments**, but ideas under consideration based on community feedback and framework evolution.

---

## v1.1.0 - "Powerful API" update (released)

### Features
- [x] Built-in rate limiting per authorized user
- [x] Multi-Database Support (named connections)
- [x] Transactions, raw SQL helpers and last error for DataService
- [x] Server-side i18n, translated mails and custom head tags
- [x] Site libraries (`src/lib/`, `Site\` namespace)
- [x] Per-site cache isolation

## v1.2.0 - "Advanced technologies" update

### Features
- [ ] Background job queue system
- [ ] API versioning support
- [ ] Built-in API token management
- [ ] WebSocket support for real-time features
- [ ] OAuth2/SSO integration helper
- [ ] ORM-like component for DataService
- [ ] NoSQL support
- [ ] Two-factor authentication helpers

### Performance
- [ ] HTTP/3 support and optimization hints
- [ ] Query result caching for DataService

### Developer Experience
- [ ] Built-in database migrations system
- [ ] Better error pages with stack traces in dev mode

---

## Contributing Ideas

Have a feature suggestion? Consider:

1. **Does it maintain Fast Raven's speed?** - Performance is non-negotiable
2. **Does it add significant complexity?** - Simplicity is a core value
3. **Is it broadly useful?** - Features should benefit most users, not edge cases

Open an issue on the repository to discuss your ideas!

---

**Last Updated:** 2026-11-03  
**Current Version:** v1.1.0
