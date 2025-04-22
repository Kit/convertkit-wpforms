#!/usr/bin/env bash

# Install Free Third Party WordPress Plugins 
wp plugin install classic-editor wpforms-lite

# Install Default WordPress Theme
wp theme install twentytwentyfive

# Symlink Plugin
ln -s /workspaces/convertkit-wpforms /wp/wp-content/plugins/integrate-convertkit-wpforms

# Run Composer in Plugin Directory to build
cd /wp/wp-content/plugins/integrate-convertkit-wpforms
composer update

# Activate Plugins
wp plugin activate wpforms-lite integrate-convertkit-wpforms