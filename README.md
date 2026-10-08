
# PHP Analytics Class

SunAnalytics is a lightweight, self-hosted and cookie-free PHP web analytics class that you can easily integrate into your small and medium sized projects.

The goal of this class is to let you; track visits, page views and online visitors, detect the traffic source of every visit (search engines, social networks, e-mail, ads, campaigns, referring sites), and get ready-made reports from your own database, without any third party service or JavaScript library.

`Technical Document:` https://www.deepwiki.com/msbatal/PHP-Analytics-Class

[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/msbatal/PHP-Analytics-Class)

<hr>

`Database` attribute: The database connection SunAnalytics uses for every query. This value may be a `SunDB` instance, a `PDO` object, or a connection parameters array (same array that `SunDB` accepts). SunAnalytics always talks to the database through `SunDB` internally.

`Salt` attribute: A secret string used to hash the visitor identity. The class never stores the raw IP address by default; a visitor is recognized by a daily rotating hash of the IP address, the user agent and the date. Set your own long random value with the `salt` config option.

`Hosts` attribute: Your own host names. A request that comes from one of them is an internal navigation, not a traffic source. It is set with the `hosts` config option.

`Config` attribute: An optional array that overrides the defaults. This value may set the table prefix and names, the visit timeout, the privacy options (raw IP, Do Not Track), the excluded IPs and paths, extra bot patterns and extra referrer rules.

<hr>

### Installation

To utilize this class, first import SunDB.php and SunAnalytics.php into your project, and require them.
SunAnalytics requires PHP 7.4+, the `mbstring` extension, MySQL/MariaDB and SunDB class to work.

```php
require_once ('SunDB.php');
require_once ('SunAnalytics.php');
```

> **Note:** SunDB is a separate dependency and is **not** bundled in this repository (a copy lives in the `test` folder only to make the demo run). Download `SunDB.php` from its own GitHub repository and add it to your project alongside `SunAnalytics.php`:
> <a href="https://github.com/msbatal/PHP-PDO-Database-Class" target="_blank">https://github.com/msbatal/PHP-PDO-Database-Class</a>

Then create the tables once (`sun_visits`, `sun_pageviews`, and `sun_online`) with the `install()` method. It honors the `prefix`, `tables`, `charset` and `collation` options, so you do not need any SQL file:

```php
$analytics->install(); // safe to run repeatedly
```

A PHP session is used to follow one visit across page views; the class starts it for you if it is not active yet.

### Initialization

Using a connection parameters array:

```php
$analytics = new SunAnalytics(['driver' => 'mysql', 'host' => 'localhost', 'dbname' => 'test', 'username' => 'root', 'password' => 'root']);
```

Using an existing SunDB instance (recommended when you already use SunDB):

```php
$db = new SunDB('mysql', 'localhost', 'root', 'root', 'test');
$analytics = new SunAnalytics($db);
```

Using an existing PDO object:

```php
$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');
$analytics = new SunAnalytics($pdo);
```

With configuration overrides:

```php
$analytics = new SunAnalytics($db, [
    'salt'         => 'a-long-random-secret', // used to hash the visitor identity
    'hosts'        => ['example.com'],        // your own host names
    'excludePaths' => ['/admin'],             // path prefixes that are never tracked
    'respectDnt'   => true                    // skip visitors that send Do Not Track
]);
```

### Tracking

Call `track()` once per page view, as early as possible (before any output, so the session can start).

```php
$analytics = new SunAnalytics($db);
$visitId = $analytics->track(); // starts a visit when needed, records the page view, refreshes the online visitor
```

`track()` returns the visit id, or `false` when the request was not tracked (bot, CLI, excluded path or IP, Do Not Track, or an error). It never breaks your page; read the reason with `lastError()`.

A visit ends after 30 minutes without a request (`sessionTimeout`). The first request of a visit decides its traffic source. Bots and requests without a user agent are never tracked.

### Tracking Cached or Static Pages

When the HTML is served from a cache, track from the browser instead. Add this to the page:

```html
<script>
fetch('/analytics.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: new URLSearchParams({
        ref: document.referrer || '',
        url: location.pathname + location.search,
        title: document.title || ''
    })
});
</script>
```

and track it in `analytics.php`:

```php
$analytics->track([
    'referrer' => isset($_POST['ref'])   ? $_POST['ref']   : '',  // document.referrer
    'url'      => isset($_POST['url'])   ? $_POST['url']   : '/', // landing path + query string (used for UTM tags and click ids)
    'path'     => isset($_POST['url'])   ? $_POST['url']   : '/',
    'title'    => isset($_POST['title']) ? $_POST['title'] : ''
]);
```

Options of `track()`: `path`, `url`, `referrer` and `title`. Without options the current request is used.

### Traffic Sources

`detectSource()` classifies a visit (it is used by `track()` and is also public if you only need the classification). It returns `source`, `medium`, `campaign`, `term`, `content` and `referrer_host`.

```php
$analytics->detectSource('https://www.google.com/', '/product');
// ['source' => 'google', 'medium' => 'organic', ...]

$analytics->detectSource('', '/?utm_source=newsletter&utm_medium=email&utm_campaign=october');
// ['source' => 'newsletter', 'medium' => 'email', 'campaign' => 'october', ...]
```

The signals are checked in this order:

| Priority | Signal | Result |
|----------|--------|--------|
| 1 | `utm_source` (+ `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) | source = `utm_source`, medium = normalized `utm_medium` (`cpc`, `social`, `email`, `display` or the value itself; `campaign` if missing) |
| 2 | `gclid`, `gbraid`, `wbraid`, `gad_source`, `msclkid`, `ttclid`, `yclid` | `google` / `bing` / `tiktok` / `yandex`, medium `cpc` (an ad click also carries a search referrer, so ad ids win) |
| 3 | Referrer host | search engines `organic`, social networks `social`, webmail `email`, any other host `referral` (source = host) |
| 4 | `fbclid`, `igshid`, `twclid` without a referrer | in-app browsers: `facebook` / `instagram` / `twitter`, medium `social` |
| 5 | none (or your own host) | `direct` / `direct` |

Add your own referrer rules (checked before the built-in ones) with the `sources` config option:

```php
$analytics = new SunAnalytics($db, [
    'sources' => [
        '/(^|\.)sahibinden\.com$/'     => ['sahibinden', 'referral'],
        '/^newsletter\.example\.com$/' => ['newsletter', 'email']
    ]
]);
```

### Reports

Every report takes a period: a number of days (the last N days including today, default 30), `'today'`, `'yesterday'`, or a `[from, to]` pair of dates.

#### Summary

```php
$summary = $analytics->summary(30);
// ['visits' => 1240, 'pageviews' => 3105, 'visitors' => 980, 'bounces' => 610, 'bounce_rate' => 49.2, 'pages_per_visit' => 2.5]
```

#### Daily Visits

```php
$days = $analytics->daily(14); // days without a visit are included with zeros
// ['2026-10-01' => ['visits' => 41, 'pageviews' => 96, 'visitors' => 37], ...]
```

#### Traffic Sources (Report)

```php
$sources = $analytics->sources(30, 5); // period, limit
// [['name' => 'google', 'medium' => 'organic', 'visits' => 540, 'pageviews' => 1302, 'bounce_rate' => 44.1, 'share' => 43.5], ...]
```

#### Breakdown by Dimension

```php
$campaigns = $analytics->breakdown('campaign', 30, 10); // dimension, period, limit
```

Dimensions: `source`, `medium`, `campaign`, `term`, `content`, `referrer`, `landing`, `device`, `browser`, `os`, `language`. Each row has `name`, `visits`, `pageviews`, `bounce_rate` and `share`. Empty values of `campaign`, `term`, `content`, `referrer`, `landing` and `language` are left out.

#### Most Viewed Pages

```php
$pages = $analytics->pages(30, 10);
// [['path' => '/product/smart-lock', 'views' => 220, 'visits' => 180], ...]
```

#### Online Visitors

```php
echo $analytics->online(); // visitors seen in the last 5 minutes (change it with the "onlineWindow" option or pass seconds)
```

> **Note:** `visitors` is counted **per day**: the visitor hash rotates every midnight (that is what keeps it anonymous). Across several days it is a sum of daily unique visitors, not distinct people. `bounce_rate` is the share of visits with a single page view.

### Device, Browser and Bot Detection

```php
$analytics->parseAgent();                // ['device' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS'] for the current request
$analytics->parseAgent($userAgent);      // or for any user agent string
$analytics->isBot('Googlebot/2.1');      // true (empty user agents are bots, too)
```

### Cleaning Old Records

Run it from a daily cron job.

```php
$deleted = $analytics->purge(180); // deletes visits and page views older than 180 days (and stale online rows)
$deleted = $analytics->purge();    // uses the "retention" option (0 = only the online rows are cleaned)
// ['visits' => 120, 'pageviews' => 310, 'online' => 4]
```

### Error Handling

`track()` never breaks the page: failures return `false` and the message is kept for you.

```php
if ($analytics->track() === false) {
    echo $analytics->lastError(); // empty when the request was skipped on purpose (bot, CLI, excluded...)
}
```

Set the `throwErrors` option to `true` to get exceptions from `track()` instead.

### Direct Database Access

```php
$db = $analytics->db(); // the underlying SunDB instance
echo $analytics->table('visits'); // real table name, e.g. sun_visits
```

### Dashboard Example

`test/dashboard.php` is a ready-made, self-contained dashboard page built only from the report methods above: summary tiles with the change against the previous period, a daily visits and page views line chart, a daily breakdown table, most viewed pages, devices and browsers, a channel donut chart, a sources table, campaigns, referring sites and landing pages. The charts are plain inline SVG (no JavaScript library). Import `test/test.sql` (it contains about two weeks of sample traffic), set your database settings at the top of the file and open it in the browser. Copy the parts you need into your own admin panel.

### Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `prefix` | `sun_` | Table name prefix (`sun_visits`, `sun_pageviews`, `sun_online`) |
| `tables` | `[]` | Full table name overrides, e.g. `['visits' => 'my_visits']` |
| `charset` | `utf8mb4` | Table charset used by `install()` |
| `collation` | empty | Table collation used by `install()` (empty = server default) |
| `sessionKey` | `sun_analytics` | `$_SESSION` key holding the current visit |
| `sessionTimeout` | `1800` | Inactivity that ends a visit (seconds) |
| `salt` | derived | Secret used to hash the visitor identity. **Set your own** |
| `ip` | `null` | Client IP override: a string or a callable (use it behind a proxy or CDN) |
| `storeIp` | `false` | Also store the raw IP address in the visit row |
| `storePages` | `true` | Store every page view (`false` = only count them on the visit) |
| `respectDnt` | `false` | Skip visitors that send `DNT: 1` or `Sec-GPC: 1` |
| `hosts` | `[]` | Your own host names (internal navigation is not a traffic source) |
| `basePath` | empty | Sub folder removed from the stored paths (e.g. `/shop`) |
| `excludeIps` | `[]` | IP addresses that are never tracked |
| `excludePaths` | `[]` | Path prefixes that are never tracked (e.g. `/admin`) |
| `bots` | `[]` | Extra bot user agent patterns (regex fragments) |
| `sources` | `[]` | Extra referrer rules (`'host regex' => ['source', 'medium']`) |
| `onlineWindow` | `300` | Seconds a visitor counts as online |
| `retention` | `0` | Default age limit of `purge()` in days (0 = keep everything) |
| `throwErrors` | `false` | Throw from `track()` instead of returning `false` |

Get or set an option at runtime with `$analytics->config('sessionTimeout')` and `$analytics->config('sessionTimeout', 900)`.

### Database Tables

`install()` creates three InnoDB tables:

- `sun_visits`: one row per visit (visitor hash, source, medium, campaign, term, content, referrer host, landing path, device, browser, OS, language, optional IP, page count, timestamps)
- `sun_pageviews`: one row per page view (visit id, path, title, timestamp)
- `sun_online`: one row per currently active visitor hash

### Privacy

- The class sets no cookie of its own; the PHP session cookie you already use follows the visit.
- The raw IP address is not stored (`storeIp` is off). The visitor hash is `HMAC-SHA256(ip | user agent | date, salt)` truncated to 32 characters; it cannot be traced back to an IP and it changes every day.
- Only the referrer **host** is stored, never the full referrer URL, and page paths are stored without query strings.
- Whether a consent banner is required for first-party, cookie-free statistics depends on your jurisdiction and setup; check with your legal advisor.

### Limitations

- Referrer data depends on the browser: in-app browsers and some e-mail clients send none and show up as `direct` unless the link carries UTM tags.
- After a visit times out, an internal navigation starts a new `direct` visit (the original source is not carried over).
- MySQL/MariaDB only (uses `INSERT ... ON DUPLICATE KEY UPDATE`); SQLite is not supported.

### Help

For more information about the UTM parameters; Please visit the Google Analytics documentation's "Campaigns and traffic sources" section.

<a href="https://support.google.com/analytics/answer/1033863" target="_blank">Campaigns and traffic sources</a>

<a href="https://github.com/msbatal/PHP-PDO-Database-Class" target="_blank">SunDB (PHP PDO Database Class)</a>
