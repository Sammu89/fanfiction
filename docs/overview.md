# Fanfiction Manager Plugin - Executive Overview

## Core Purpose
Enable independent fanfiction communities to operate on WordPress without relying on external platforms, while maintaining full data ownership and customization control.

## Target Users
- Fanfiction authors (creators of stories)
- Fanfiction readers (consumers of stories)
- Community moderators (content managers)
- Site administrators (technical management)

## What the Plugin Does

### For Authors
- Provides a frontend-only interface for creating and managing stories without accessing the WordPress admin dashboard.
- Enables hierarchical story organization (intro + multiple chapters + optional prologue/epilogue).
- Offers draft/publish workflow with automatic validation.
- Allows categorization by genres, status, and custom taxonomies (fandoms, ratings, pairings, etc.).
- They never access the backend of WordPress.

### For Readers
- Creates a searchable, filterable story archive with advanced filtering by genre, status, author, and custom taxonomies.
- Provides a comfortable reading experience with optimized typography, keyboard navigation, and reading progress tracking.
- Enables community features: following stories, following authors, rating chapters, and commenting.
- Maintains personal libraries (favorites, reading history, followed authors).
- They never access the backend of WordPress.

### For Moderators
- Provides content moderation tools to review, approve, or delete reported stories and comments.
- Enables user management (suspending banned users while preserving their private content).
- Tracks all content modifications with moderation stamps showing who edited what and when.

### For Administrators
- Offers configuration pages for customizing the plugin (base URL, custom taxonomies, notification templates, custom CSS).
- Provides analytics dashboard showing platform statistics.
- Enables bulk taxonomy management (creating, editing, deleting content categories).

## Architecture Philosophy
- **Separation of Concerns:** The plugin separates frontend user experiences (author dashboard, reading interface, discovery) from WordPress admin functions. Authors and readers never see the WordPress admin dashboard.
- **Data Ownership:** All plugin data is stored in WordPress (not external services), ensuring complete portability and no vendor lock-in.
- **Performance First:** The plugin prioritizes server performance on resource-constrained hosting through caching strategies (transients for expensive queries), efficient database queries (proper indexing, pagination), and deterministic chapter-view dedupe using indexed tables.
- **Modularity:** The plugin is organized into focused, single-responsibility classes that can be maintained and tested independently.
- **Extensibility:** Theme developers and plugin developers can extend the plugin through standard WordPress hooks and by overriding templates.

## Documentation Map

### Backend Documents
- [Backend Documents](backend/README.md)
- [Translated Stories](backend/translated-stories.md)
- [Status System](backend/status-system.md)
- [Shortcodes](backend/shortcodes.md)

## Summary
This architecture specification provides a comprehensive blueprint for implementing the Fanfiction Manager plugin. It covers all core functionality, user roles, content management, moderation, performance, and accessibility considerations.

Key Design Principles Applied:
- Separation of Concerns: Frontend author experience separate from WordPress admin.
- Performance First: Caching, indexing, pagination, lazy loading.
- Extensibility: Theme template overrides, developer hooks, standard WordPress integration.
- Security: Role-based access, nonce verification, sanitization, escaping.
- User Experience: Responsive design, accessibility (WCAG AA), intuitive navigation.

Next Steps:
1. Finalize architectural decisions.
2. Create detailed API documentation for each class.
3. Begin Phase 1 implementation (see implementation-checklist.md).
4. Establish code review and testing processes.

This document serves as a living blueprint—it can be updated as implementation progresses and new requirements emerge.
