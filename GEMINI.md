## Project Overview

This repository contains the Fanfiction Manager, a comprehensive WordPress plugin designed to transform a standard WordPress site into a fully-featured fanfiction archive. It provides a modular and robust platform for publishing and managing fanfiction stories.

The plugin's architecture is built around a central core file (`class-fanfic-core.php`) that loads all dependencies in a specific order, ensuring stability. It uses a singleton pattern for its main classes. Key features include custom post types for stories and chapters, custom taxonomies for organization (like genre and status), and a complete user role system with capabilities for authors, moderators, and administrators.

The frontend is primarily driven by a powerful shortcode system and custom page templates, allowing for flexible integration into various themes. It also features a centralized URL manager for creating custom, user-friendly URLs and a sophisticated homepage state system that supports multiple layout configurations. Security and performance are handled through dedicated classes for nonce validation, input sanitization, rate limiting, and a transient-based caching layer.

## Directory Structure

The project is organized into the following key directories:

-   `fanfiction-manager.php`: The main plugin file and entry point. It initializes the core class.
-   `includes/`: This is the heart of the plugin, containing all the core PHP classes.
    -   `includes/admin/`: Classes specifically for the WordPress admin area.
    -   `includes/handlers/`: Classes that process frontend requests for major components like stories, chapters, and user profiles.
    -   `includes/shortcodes/`: A large collection of classes, each dedicated to a specific shortcode for displaying content or forms on the frontend.
    -   `includes/widgets/`: Classes for any custom WordPress widgets provided by the plugin.
-   `templates/`: Contains the PHP template files for rendering the frontend pages, such as the story archive, chapter view, and user dashboards. These are used as fallbacks if the active theme doesn't provide its own templates.
-   `assets/`: Holds all the static frontend assets.
    -   `assets/css/`: Contains the CSS stylesheets for the plugin's frontend and admin interfaces.
    -   `assets/js/`: Contains the JavaScript files that add interactive functionality to the frontend, such as AJAX-powered features (likes, ratings) and the setup wizard.
-   `database/`: Contains schema information and data dumps, like lists of fandoms and languages, used for populating taxonomies.
-   `docs/`: A comprehensive collection of markdown files detailing the plugin's architecture, data models, features, and implementation notes.
-   `Implementation/`: Contains planning documents and checklists related to the development and rollout of features.
