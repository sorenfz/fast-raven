## [v1.1.0] - 2026-11-03
#### Requirements
- **PHP ^8.5** is now required. The framework uses `array_first()`, the pipe operator and `#[\NoDiscard]` on factories and getters whose result must not be dropped.

#### New features
- DataService::use() selects a named database connection for the rest of the request. Named connections read `DB_<NAME>_*` environment variables and inherit missing keys from `DB_*`.
- DataService::sqlOne(), DataService::sqlValue(), DataService::transaction() and DataService::getLastError().
- Condition::isNull(), Condition::isNotNull(); Condition::is() / Condition::isNot() now take `?bool` and render `IS TRUE / FALSE / NULL` literals.
- Condition::getBindValues() and ConditionList::getBindValues() return the flat list of values to bind.
- AuthService::setAuthorizedUserCustomData() for replacing the custom data of an authorized session.
- AuthService::getAuthorizedUserCustomData() for retrieving custom data from authorized sessions.
- Server-side i18n: pages render in the client's preferred language (`lang` cookie, then `Accept-Language`, then default) and `<html lang>` is set accordingly.
- Template::lang(), Template::setLang() / getLang(), Template::resolveLang(); Bee::lang() and Bee::langData() for translations anywhere.
- Request::getPreferredLanguages() exposes the client language preferences.
- Mail::setLang() translates the `{{KEY}}` placeholders left in subject and body after the replace values.
- Template::addHead() and Template::addMeta() for custom `<head>` tags (SEO, Open Graph, canonical...).
- Site libraries: classes under the `Site\` namespace are autoloaded from the site's `src/lib/` folder (ProjectFolderType::SRC_LIB).
- CacheService isolates every site with a `<domain>:<site>:` key prefix; CacheService::empty() only clears the current site on APCu.
- Config::configureProxy() takes the client IP and protocol from X-Forwarded-For / X-Forwarded-Proto behind a trusted reverse proxy.
- UnhandledException wraps any non-framework Throwable escaping the request lifecycle into a logged 500 instead of a raw PHP fatal.
- PayloadTooLargeException: request bodies above the configured input length limit are answered with 413 instead of being silently truncated (multipart uploads are bounded by the file upload limit instead).
- Skeleton: ops/deploy.sh denies direct access to `config/`, `src/` and `storage/` in the generated vhost.
- Skeleton: `src/lib/PongRepository.php` example, `addMeta()` in template config, language switch button in the ping page.

#### Changes
- **Rate limiting now targets authorized users instead of IPs.** Anonymous traffic is not limited (that is the reverse proxy's job) and receives no `RateLimit-*` headers.
- DataService::sql() returns the rows for result sets and the affected row count for any other statement (previously `true` for UPDATE and `rowCount() > 0` for DELETE). Statement type is no longer sniffed from the query text.
- Condition::between() / notBetween() take a low and a high bound.
- Bee::parseCSV-based language data is loaded through Bee::langData(); Template::getHtmlLang() still injects it for client-side switching.
- Lib.changeLanguage() persists the choice in the `lang` cookie instead of `localStorage` and no longer re-translates the page on load (the backend already did). `Lib.CURRENT_LANGUAGE` reflects `<html lang>`.
- Template::merge() also merges the explicit language and head tags.
- View error redirects (not found, unauthorized, middleware denied) are skipped when they target the page that just failed; the error page is shown instead of looping.
- Cache-Control is `no-store` for anything served to an authorized session, so private pages and CDN files are not kept by the browser after logout.
- CacheService::increment() / decrement() are atomic on every backend (file lock), and the rate limiter uses them instead of read-then-write.
- Bee::getBaseDomain() keeps three labels for two-letter TLDs preceded by a short label (`example.co.uk`, `example.com.au`).
- Kernel initializes the session and headers before rate limiting, and resolves the page language before the view handler runs so `$baseTemplate->lang()` works inside handlers.
- MiddlewareDeniedException, exclusively thrown on views, now redirects to "/" page instead of showing generic error page.
- Skeleton: .gitignore ignores `config/env/env.php`, `storage/cache`, `storage/logs` and `storage/uploads` contents while keeping `.gitkeep`; stale `.env*` and `/logs` entries removed (also in ops/build.sh).
- Skeleton: ops/build.sh creates `src/lib/`, `.gitkeep` files for compiled assets, a placeholder `storage/uploads/logo.png`, and generates the same Pong/Logo/ping examples as the skeleton.
- Framework: `.phpunit.result.cache` is ignored and no longer committed.

#### Fixes
- *Nested routers ignored the `restricted` flag and middleware of their Endpoint::router() entry.*
- *Config and router rate limits shared one counter, so a request counted twice when both were set; counters are now per endpoint type.*
- *PHP's session cache limiter overrode the framework Cache-Control header once a session started.*
- *A JSON body that is not an object (e.g., a bare string) made Request::post() throw a TypeError.*
- *Condition::in() / notIn() bound the array as a single parameter, making IN conditions fail.*
- *Condition::between() generated invalid SQL.*
- *Condition::is() bound a placeholder, which MySQL rejects after IS.*
- *Boolean condition values were bound as strings.*
- *Kernel::close() crashed with an uninitialized property error when an exception was thrown before all engines were created (rate limit exceeded, restricted site with anonymous user) or when request logging was disabled.*
- *CacheService::runGarbageCollector() threw UnhandledMatchError on APCu and shmop backends.*
- *Undefined `$_SERVER["HTTPS"]` warning behind reverse proxies.*
- *DataService::getLastInsertId() returned 0 instead of null when no connection could be established.*
- *MailService does not set UTF-8 charset format when sending emails.*
- *Docs referenced removed APIs (Collection, Item, ValidationFlags, ValidationService::username).*

#### Upgrade notes
- Create `src/lib/` in every site (dev mode validates the skeleton).
- Replace empty `<span data-lang="KEY"></span>` elements with `<span data-lang="KEY"><?= $template->lang("KEY") ?></span>`; `localStorage["activeLang"]` is ignored.
- Update `Condition::between($col, $value)` calls to `Condition::between($col, $low, $high)` and `Condition::is($col, "...")` to `?bool` or `Condition::isNull()`.
- Code relying on `DataService::sql()` returning `true` for UPDATE must treat `0` affected rows as falsy.
- Rate limits are per authorized user: configure IP-based limits in the reverse proxy.

### [v1.0.0] - 2026-02-01
#### Architecture
- **MAJOR CHANGE**: Workers are now called Services
- **MAJOR CHANGE**: Slaves are now called Engines
- **MAJOR CHANGE**: Namespaces verbosity improved.
- Removed DotEnv library and replaced with native php environment files.
- Framework DOM scripts are now compiled through ops/compile.sh and loaded as a legit script in public.

#### New features
- Template::setErrorPage() for linking an error page to a specific error code.
- Configurable shmop max size via SHMOP_MAX_SIZE environment variable.
- Support for external scripts and styles in Template::addScript() and Template::addStyle().
- Support for external favicons in Template::setFavicon() and Template::setFaviconLight() and Template::setFaviconDark().
- Bee::defineEnv() for defining environment variables.
- Template::getNonce() for including inline scripts inside views.
- Skeleton: added init.sh script for quick project setup.

#### Changes
- Template::addData() now accepts string parameters instead of Pair objects.
- Bee::getCacheKey() now includes the project version in the cache key to avoid cache conflicts.
- Language data is now cached in memory for 24 hours.
- Language data cache is now cleared on dev mode.
- Language JS object is renamed to LANG_INTERNAL.
- Language current language is now stored in localStorage and retrieved on page load.
- Shmop cache backend now uses dynamic segment sizing according to data size.
- Skeleton: Operation script watch.sh is now called compile.sh.
- Skeleton: Operation scripts improved significantly.
- Bee namespace changed from FastRaven\Workers to FastRaven.
- Improved and upgraded CSP policy and base template rendering.
- Environment variables are now loaded from config/env/env.php file.

#### Fixes
- *PHP throws a warning when headers are sent before session_start().*
- *Page redirection on view error do not work.*
- *When returning baseTemplate as a View response, it duplicates styles, scripts and fragments.*
- *Request time in logs is not an accurate measurement of actual request processing time.*
- *Any Database query without conditions throws a DeveloperException.*

#### Removed features
- Stashes and by extension LogStash does not exist anymore.
- .env, .env.dev and .env.prod files have been removed from config/env/ directory.