# MEUIP.RS

BLUE3's "what is my IP" service — shows the visitor their public IP, ASN,
provider (ISP), country and region, with a web page and `curl` endpoints.

> **v2.0 (2026):** security and architecture rewrite. See [Changes](#changes-in-v20).

## 🔄 Before you start: `git pull`

**ALWAYS** check for remote updates before writing or changing anything in this repository:

```bash
git pull          # already pre-authorized (allow)
```

Working on top of an outdated base causes conflicts. Pull first, always. To just inspect beforehand: `git fetch && git status`.

## Architecture

```
meuip.rs/
├── index.php            # front controller (routing)
├── config.php           # secrets (NOT versioned — see config.example.php)
├── config.example.php   # configuration template
├── schema.sql           # meuip_visits table (audit)
├── src/                 # code (blocked from web access)
│   ├── bootstrap.php     # config + autoloader
│   ├── helpers.php       # escaping, client IP, responses
│   ├── Database.php      # single PDO, lazy, prepared statements
│   ├── GeoLookup.php     # geo via native cURL + cache + timeout
│   ├── UserAgent.php     # OS/browser
│   └── AccessLog.php     # visit logging (best-effort)
├── views/               # templates (blocked from web access)
│   ├── layout.php        # head + discreet menu + footer
│   ├── home.php          # main page
│   └── doc.php           # documentation
└── assets/style.css     # BLUE3 corporate light theme
```

## Setup

1. `cp config.example.php config.php` and fill in the credentials (or use the
   `MEUIP_DB_*` / `MEUIP_IPINFO_TOKEN` environment variables).
2. Create the visits table: `mysql -h <host> -u <user> -p <db> < meuip.rs/schema.sql`.
3. Make sure the cache directory (`cache_dir` or `sys_get_temp_dir()`) is
   writable by the PHP-FPM user.

## Endpoints

| Route | Response |
|------|----------|
| `/` | IP as text (curl) or HTML page (browser) |
| `/ip` | IP |
| `/asn` | ASN |
| `/isp` | Provider |
| `/country` | Country (code) |
| `/region` | Region |
| `/coord` | `lat,long` |
| `/all` | JSON with everything |
| `/full` | Full HTML page |
| `/trace` | Traceroute: live page that auto-starts, with optional continuous mtr-style monitoring (browser) or route to your own IP (curl) |
| `/trace/<host>` | Traceroute to a target — plain text (curl) or live page (browser) |
| `/trace/stream[?host=]` | Live traceroute via SSE (used by the page) |
| `/doc` | Documentation |

## Audit (INTRANET)

Visits are recorded in `meuip_visits`. The audit panel is in the INTRANET
admin (`adm.blue3.com.br/admin` → **Auditoria meuip.rs** card), which reads the
table via the read-only `meuip` connection (the standard for auxiliary connections). There is no
ingestion API — reading is done directly on the database.

## Changes in v2.0

- **SQL injection** eliminated — all database access uses prepared statements.
- **Credentials and token** moved to `config.php` (gitignored) + `.gitignore`.
- **XSS** fixed — all HTML output goes through `htmlspecialchars`.
- **shell_exec("curl ...")** replaced by native cURL with timeout and cache.
- **Parsing** of ASN/geo now via JSON (`json_decode`), not character offsets.
- **Logging** via `error_log`/database — no `shell_exec`. Cookies are no longer written.
- Real front controller; `/ip`, `/asn`, etc. endpoints implemented.
- Dead files removed (`Bindex.php`, `full.php`, `meu.php`, `ip.php`, stubs).
