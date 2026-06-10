# MiniCMS

A template-first CMS aspect for [fubber/mini](https://github.com/frodeborli/fubber-mini). Adds inline editing, a media library, auto CRUD for database models, an admin panel, and an AI assistant — all layered on top of a plain Mini application without changing how the app itself works.

## Requirements

- PHP 8.3+
- [fubber/mini](https://github.com/frodeborli/fubber-mini) ^0.17.0

## Installation

```bash
composer require fubber/mini-cms
```

## Quick start

Create the content directory structure your site needs:

```
_content/
  routes.php       # Page definitions
  models.php       # Data model registrations
  site.json        # Site name and description
_views/            # Your templates (override CMS defaults)
_static/           # Your CSS, images, etc.
uploads/           # Media uploads (created at runtime)
```

### Define pages

In `_content/routes.php`, map URL paths to pages:

```php
<?php
use MiniCms\Page;

return [
    '/'      => new Page('pages/home', title: 'Home'),
    '/about' => new Page('pages/about', title: 'About'),
];
```

Each page points to a view template in `_views/`. Views are plain PHP using Mini's template inheritance.

### Inline-editable content

Use helper functions in your views to create regions the admin can edit:

```php
<?= cms_text('home.json', 'Hero|Heading', 1, 'Welcome', 'h1') ?>
<?= cms_html('home.json', 'Hero|Body', 2, '<p>Default content here.</p>') ?>
<?= cms_image('home.json', 'Hero|Background', 3, '/default.jpg', 'img', 'Alt text', '16x9') ?>
```

Content is stored as JSON files in `_content/`. When the admin is logged in, these regions become editable in-place.

### Data models

Register database models for auto CRUD in `_content/models.php`:

```php
<?php
use MiniCms\Entity;
use App\Testimonial;

return [
    'testimonials' => (new Entity(Testimonial::class, icon: 'bi-chat-quote', pluralTitle: 'Testimonials'))
        ->withDefaultOrder('sort_order ASC'),
];
```

The admin panel automatically provides list, create, edit, and detail views. Customize any view by overriding it:

```php
'testimonials' => (new Entity(Testimonial::class, icon: 'bi-chat-quote'))
    ->withIndexView('admin/data/testimonials/index.php')
    ->withEditView('admin/data/testimonials/edit.php'),
```

### Entity relationships

When a model has a `#[ForeignKey]` attribute pointing to another registered entity, the CMS renders the field as a searchable picker in forms and as an inline reference in detail views.

```php
#[ForeignKey(navigation: 'customer')]
public int $customer_id;
```

## Admin panel

The admin panel lives under `/admin/` and provides:

- **Page editor** — inline editing with a component sidebar
- **Media library** — upload, browse, crop, and organize images
- **Data management** — auto-generated CRUD for registered models
- **Site settings** — edit site name and metadata
- **AI assistant** — chat with an AI agent that can read and edit your site files

Access the admin at `/admin/` after setting `CMS_PASSWORD` in your `.env` file.

## AI assistant

The CMS includes an AI assistant available as a drawer in the page editor and as a standalone page at `/admin/ai/`. It uses an adapter pattern — the default implementation (`ClaudeCodeAgent`) invokes the Claude Code CLI, but other backends can be swapped in by registering a different `AgentInterface` implementation.

The assistant has full context of your site structure and can read and modify files directly.

## How aspects overlay

MiniCMS registers its routes, views, and static assets through Mini's path registry. Your application's files always take priority — if you define `_views/partials/header.php`, it overrides the CMS default. This lets you customize any part of the admin UI or public-facing templates without forking the package.

## Building JS assets

If you need to modify the CMS JavaScript:

```bash
cd vendor/fubber/mini-cms
npm install
npm run build    # one-time build
npm run watch    # rebuild on changes
```

The bundle outputs to `_static/admin/dist/cms.min.js`. The built file is committed to the repo, so end users don't need Node.js.

## Constraints

- **No external CDNs.** All assets (Bootstrap, AdminLTE, fonts, icons) are served locally. This is a privacy requirement.
- **Filesystem content + git = versioning.** All content lives in flat files, so your entire site is version-controlled by default.

## License

[MIT](LICENSE)
