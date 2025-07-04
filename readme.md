# Import External Images

A WordPress plugin to **batch import external images in posts**, save them locally, and update post content to use the local images. Supports search, filter, pagination, batch import, and TinyPNG compression.

## Features

- Batch import external images in published posts
- Save images to a fixed directory (`wp-content/post-images`)
- Replace external image URLs in post content with local URLs
- Supports search by post title
- Filter by post status
- Pagination for large numbers of posts (20 per page)
- Batch import with progress feedback
- TinyPNG API integration for automatic image compression (for images >100KB)
- Custom image naming: `postID-index.extension` (e.g., `1234-1.jpg`)
- View and clear import logs
- Simple and user-friendly admin interface

## Installation

1. Download or clone this repository to your `wp-content/plugins` directory.
2. Activate the plugin in the WordPress admin panel.
3. Go to **Settings → TinyPNG Settings** and enter your TinyPNG API key (get one at [https://tinypng.com/developers](https://tinypng.com/developers)).

## Usage

1. In the WordPress admin panel, go to **Tools → Import External Images**.
2. Use the search box or filters to find posts with external images.
3. Select the posts you want to process (supports bulk selection).
4. Click **Start Import** to batch import and compress images.
5. The plugin will download, compress (if needed), and replace external image URLs in the selected posts.
6. You can view the import log or clear it at any time.

## TinyPNG API

- The plugin uses the TinyPNG API to compress images larger than 100KB.
- You must provide your own API key in the settings page.
- Free accounts allow up to 500 compressions per month.

## Image Naming

- Imported images are saved as `postID-index.extension` (e.g., `2313-1.jpg`, `2313-2.png`).
- This avoids filename conflicts and makes it easy to trace images to their source post.

## Requirements

- WordPress 5.0 or higher
- PHP 5.6 or higher (PHP 7+ recommended)
- PHP cURL extension enabled