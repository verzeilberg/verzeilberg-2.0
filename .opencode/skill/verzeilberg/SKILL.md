---
name: verzeilberg
description: Use when working on the verzeilberg-2.0-dev Symfony project. Covers project structure, common commands, testing, and Symfony 8.1 conventions.
---

# Verzeilberg Project Skill

This skill provides context for working on the **verzeilberg-2.0-dev** Symfony 8.1 project.

## Project Overview

- **Framework**: Symfony 8.1 (PHP 8.4+)
- **ORM**: Doctrine ORM 3.6 with Doctrine Migrations
- **Templating**: Twig 3.x
- **Testing**: PHPUnit 13.3
- **Frontend**: AssetMapper + Stimulus + UX Turbo
- **Database**: Configured via `.env` files (Docker Compose available)

## Key Commands

```bash
# Development
composer install
symfony serve -d

# Database
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate

# Testing
bin/phpunit

# Cache
bin/console cache:clear

# Assets
symfony console asset-map:compile
```

## Project Structure

```
src/
  Controller/    # Controllers (empty, ready for implementation)
  Entity/        # Doctrine entities (empty)
  Repository/    # Doctrine repositories (empty)
  Kernel.php     # MicroKernel setup

config/
  packages/      # Symfony package configurations
  routes.yaml    # Main routing
  services.yaml  # Service definitions

templates/
  base.html.twig # Base Twig template
```

## Symfony 8.1 Conventions

- Use PHP 8.4+ syntax (property hooks, readonly, etc.)
- Controllers as invokable classes or single-action classes
- Dependency injection via constructor injection
- Doctrine entities with typed properties and PHP 8 attributes
- Routing via attributes (`#[Route]`)
- Validation via attributes (`#[Assert\...]`)

## Testing

- Tests in `tests/` (PSR-4: `App\Tests\`)
- Run with `bin/phpunit`
- Kernel boots in `tests/bootstrap.php` if needed

## Docker

- `compose.yaml` for services
- `.env.dev` / `.env.test` for environment config
- Database likely in Docker (check compose.yaml)

## Common Tasks

### Create Entity
```bash
bin/console make:entity
```

### Create Controller
```bash
bin/console make:controller
```

### Create Migration
```bash
bin/console make:migration
bin/console doctrine:migrations:migrate
```

### Run Tests
```bash
bin/phpunit                    # All tests
bin/phpunit --filter=TestName  # Specific test
```

## Code Style

- PSR-12 compliant
- PHPStan/Psalm for static analysis (if configured)
- Symfony coding standards
- **Delete confirmations**: Use Bootstrap modals instead of JavaScript `confirm()` prompts. The project has a reusable delete confirmation modal pattern in `admin/base.html.twig` (id: `deleteModal`) that should be used for all delete actions.

### Delete Modal Usage

```twig
{% include 'admin/partials/delete_modal.html.twig' %}

<button type="button" class="btn btn-outline-danger delete-btn" 
        data-bs-toggle="modal" 
        data-bs-target="#deleteModal"
        data-delete-url="{{ path('route_name', {id: item.id}) }}"
        data-csrf-token="{{ csrf_token('delete' ~ item.id) }}">
    <i class="fas fa-trash"></i>
</button>
```

The modal handles CSRF token and AJAX delete request automatically.

## Migration from Old Project

This project is the new version of **verzeilberg.nl**, replacing the old project at `/var/www/verzeilberg-dev`.

### Migration Task
Convert all modules from the old project to this new Symfony 8.1 codebase with improvements:

- **Source**: `/var/www/verzeilberg-dev` (legacy project)
- **Target**: `/var/www/verzeilberg-2.0-dev` (this Symfony 8.1 project)
- **Goal**: Migrate all existing modules, modernize code to PHP 8.4+/Symfony 8.1 conventions, and add improvements

### Key Migration Considerations
- Analyze existing modules in the old project first
- Refactor to use Symfony 8.1 patterns (attributes, PHP 8.4+ syntax, invokable controllers)
- Use Doctrine ORM 3.6 with typed entities
- Leverage AssetMapper + Stimulus + UX Turbo for frontend
- Maintain data compatibility (plan migrations carefully)
- Improve architecture where possible (services, repositories, CQRS if applicable)

## Module Migration Tracker

Track progress migrating modules from the old Laminas project:

### Core Application Modules
- [x] **Application** - Core app module (entities: Menu, MenuItem, UserDetails ✅, controllers: HomeController, MenuController ✅, templates ✅)
- [x] **User** - User management/authentication (entities: User, Role, Permission ✅, fixtures ✅)

### Development Tools
- [x] **Fixtures** - DoctrineFixturesBundle with Faker for dev data
- [x] **Static Analysis** - PHPStan level 5 with Doctrine/Symfony extensions
- [x] **Blog** - Blog posts/articles (entities: BlogPost, BlogCategory, BlogComment, BlogImage, BlogFile, BlogYouTube ✅, controllers + templates ✅)
- [ ] **Agenda** - Calendar/events
- [ ] **Event** - Event management
- [ ] **Contact** - Contact forms
- [ ] **Search** - Search functionality
- [ ] **CheckList** - Checklist/todo functionality

### File/Image Handling
- [ ] **UploadFiles** - File uploads
- [ ] **UploadImages** - Image uploads/management
- [ ] **AssetManager** - Asset management

### Social & API Integrations
- [ ] **Twitter** - Twitter integration
- [ ] **YouTube** - YouTube integration
- [ ] **StravaApi** - Strava API
- [ ] **SteamApi** - Steam API
- [ ] **BitlyShortener** - URL shortening
- [ ] **GoogleAgendaApi** - Google Calendar (disabled in old project, evaluate if needed)

### Email & Utilities
- [ ] **Email** - Email sending/templates

### Infrastructure (Laminas/Doctrine - handled by Symfony bundles)
- [ ] Laminas components → Symfony equivalents
- [ ] DoctrineModule/DoctrineORMModule → DoctrineBundle

### Legend
- `[ ]` = Not started
- `[~]` = In progress
- `[x]` = Completed
- `[!]` = Blocked/needs decision
- `[-]` = Skipped/not needed