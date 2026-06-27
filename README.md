# IA Inbound

An unofficial Omeka S admin module that imports [Internet Archive](https://archive.org/) items into Omeka using the internal `Omeka\Api\Manager` (no REST API keys required for import).

## Built for a Georgian / English archive

This module was custom-made for a Georgian and English archive workflow. It is intended to work well for:

- **Monolingual items**
- **Bilingual items** where Internet Archive stores English and Georgian together (split on ` | ` and ` / ` delimiters, with script-based detection)

**Other language combinations may not import cleanly.** The module can resolve some Internet Archive language codes into Omeka language values, but multi-lingual items that mix languages beyond English and Georgian may produce unexpected titles, subjects, descriptions, or language metadata.

## Data backup and integrity

**Always check imported items in Omeka.** The import module is not perfect. Titles, subjects, and descriptions can be concatenated or split incorrectly. Language values are taken from Internet Archive when present; image items often have no IA language and will not get a default. Open each imported item and confirm the metadata matches what you expect on Internet Archive before publishing or pushing back with IA Outbound.

The Import screen and setup checklist repeat this reminder.

It is recommended to always keep a backup of both the Omeka database and the Internet Archive items in case of any data loss.

To backup the Omeka database, you can use the following command in the Ghent Docker repository:

```bash
docker compose exec -T db mariadb-dump -uomeka -pomeka omeka > ../backups/omeka-backup-$(date +%Y%m%d).sql
```

Docker must be running for the command to work.

The command works as follows:

- `docker compose exec -T db` - execute a command in the database container
- `mariadb-dump` - dump the database
- `-uomeka -pomeka` - use the omeka user and password 
  - this is defined in the `.env` file in Ghent Docker
  - for example, if:
    - `MYSQL_USER=omeka`
      - use `-uomeka` (the -u flag attached to `omeka` defines the user to use for the database)
    - `MYSQL_PASSWORD=omeka`
      - use `-pomeka` (the -p flag attached to `omeka` defines the password to use for the database)
- `omeka` - the database name
  - defined as `MYSQL_DATABASE=omeka` in the `.env` file in Ghent Docker
- `> ../backups/omeka-backup-$(date +%Y%m%d).sql` - the backup file name (if no backup directory exists it must be made manually, for example run `mkdir -p ../backups` from the Ghent Docker root directory.)

This module will not overwrite any item on the Internet Archive. You may still want to keep your own record of IA items separately.

## Requirements

- Omeka S **4.0+**
- Background **jobs** (Omeka dispatches PHP CLI workers; verify under Admin → Jobs)
- **IIIF Presentation** media ingester (Omeka core) for image and single-PDF IIIF rows; other item types still import with thumbnail and Internet Archive embed

## Install (assumes use of [Ghent Docker](https://github.com/GhentCDH/Omeka-S-Docker))

1. Install the module files under `modules/InternetArchiveInboundSync/` (bind-mount, git clone, or ZIP URL in `OMEKA_S_MODULES` — see below).
2. **Admin → Modules → Install → Activate**.
3. Open **IA Inbound → Import** — expand the setup checklist for anything still needed.
4. **Modules → Configure** — set default resource template, optional item set/sites, batch tuning.
5. Dry-run one identifier, confirm **Admin → Jobs**, then run a small live import.
6. Open the imported items in Omeka and review metadata before relying on it.

Note: this module has not been tested with other Docker setups.

### Bind-mounting the module (Ghent Docker)

File: `compose.override.yaml`

```yaml
services:
  omeka:
    volumes:
      - ../InternetArchiveInboundSync:/volume/modules/InternetArchiveInboundSync
```

Use this for active development: edit the module repo, commit, and push; pull changes into each checkout as needed.

### Installing from a release ZIP (Ghent Docker)

Add a GitHub Release zip URL to `OMEKA_S_MODULES` in `.env`:

```env
OMEKA_S_MODULES="Common Contribute … https://github.com/YOUR_USER/InternetArchiveInboundSync/releases/download/v1.3.0/InternetArchiveInboundSync.zip"
```

On container start, Ghent Docker downloads the zip into `data/omeka/modules/` if that folder does not already exist. To upgrade, remove the module directory, bump the URL, restart the container, then **Admin → Modules → Upgrade**.

On install, the module auto-selects **Base Resource** (or the only resource template) and all existing sites as import defaults when those settings are empty.

### Choosing bind-mount vs ZIP URL

| Approach | Best for |
|----------|----------|
| **Bind-mount** | Development; git pull in the mounted repo updates the running code |
| **ZIP URL in `.env`** | Simpler instance setup without compose overrides per module |

ZIP install extracts plain files (not a git repository). To publish module changes, release a new zip and update `.env` on each instance.

## Admin UI

- **IA Inbound → Import** — setup checklist, metadata review notice, collection/identifiers/URLs, dry-run, batch jobs
- **IA Inbound → History** — per-run stats and logs
- **Modules → Configure** — import defaults, User-Agent, batch tuning (same checklist shown)

## Import behaviour (summary)

| Topic | Behaviour |
|-------|-----------|
| Sources | IA collection ID, identifier list, and/or archive.org URLs (merged and deduplicated) |
| Titles / creators / descriptions | Split on bilingual delimiters; script detection assigns `en` or `ka` tags |
| Subjects | Split on `;`; each value gets a script-based language tag |
| Language property | Resolved from IA `language` via ISO/MARC lookup and Georgian label pairs; **no default** when IA has no language |
| Sync modes | Create only, or update existing items (optional metadata overwrite) |
| Media | Thumbnail, optional IIIF Presentation row, Internet Archive embed iframe |

## Media rows

| Type | Thumbnail | IIIF Presentation | IA embed |
|------|-----------|-------------------|----------|
| Image | yes | yes | yes |
| Single-PDF text | yes | yes | yes |
| Multi-PDF text | yes | no | yes |
| Video / audio | yes | no | yes |
| Subpath (`parent/sub`) | yes | no | yes |

IIIF Presentation is not added for multi-PDF texts or video/audio items due to IIIF reliability issues during testing. Internet Archive embed is still available.

## Administrator responsibilities (not automatic)

The Import and Configure screens list these explicitly:

| Topic | Why |
|-------|-----|
| Default resource template | Required for every import |
| Background jobs | Imports queue as jobs |
| Review imported metadata | Titles, subjects, descriptions, and languages may need manual correction |
| HTML iframe policy | Purifier may strip embed iframes |
| Site Media resource page | Add **Media render** block so `/s/{site}/media/{id}` shows thumb/IIIF/embed |

To change the iframe policy, go to **Admin → Settings → HTML purifier** and allow iframes.

## Tests

Quick smoke tests (no Composer):

```bash
php test/smoke.php
```

PHPUnit (from module root):

```bash
composer install
composer test
```

## Companion module

**InternetArchiveOutboundSync** (IA Outbound) pushes Omeka metadata back to Internet Archive. Review inbound results before using outbound on the same items. The two modules are optional companions, not hard dependencies.
