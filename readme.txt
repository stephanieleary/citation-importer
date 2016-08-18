# Citation Importer
Contributors: sillybean
Tags: academic, bibliography, citations, crossref, doi, scholar
Donate link: http://stephanieleary.com/code/wordpress/wordpress-citation-importer/
Requires at least: 3.0
Tested up to: 4.6
Stable tag: 0.5
License: GPL2
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Import a citation or bibliography as posts.

## Description
The WordPress Citation Importer plugin imports individual citations, bibliography lists, or lists of DOIs into the WordPress publication database. You may choose which post type to use as the destination. Some custom fields and a taxonomy are specified, but can be filtered (as can the post fields themselves).

The plugin uses the [CrossRef Metadata API](http://search.crossref.org/help/api) to retrieve complete publication information using the citation as a search query.

## Screenshots
1. The citation entry screen
2. Confirming selected publications to import
3. Importing
4. Correcting typos after import

## Changelog

### 0.5
* Sanitize imported data
* Add sample filters file

### 0.4.3
* Remove verbose errors
* Suppress XML errors
* Fix filter empty text reference
* String cleanup

### 0.4.2
* Fix column IDs

### 0.4.1
* Remove abstract until it's incorporated into the API

### 0.4
* Add progress bar, batch pause, and date formatting
* Remove short DOI, as it is not always present

### 0.3.3
* Move post type logic
* Add labels and nonces

### 0.3.2
* Use home_url() for user agent

### 0.3.1
* Prevent query transient index from being overwritten

### 0.3
* Initial commit
