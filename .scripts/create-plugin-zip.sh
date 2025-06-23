# Remove vendor directory
cd ..
rm -rf vendor

# Run composer to only install non-dev dependencies
composer install --no-dev

# Build ZIP file, excluding non-Plugin files
[ -e integrate-convertkit-wpforms.zip ] && rm integrate-convertkit-wpforms.zip
zip -r integrate-convertkit-wpforms.zip . \
-x "*.git*" \
-x ".devcontainer/*" \
-x ".scripts/*" \
-x ".wordpress-org/*" \
-x "log/*" \
-x "tests/*" \
-x "vendor/composer/*" \
-x "vendor/convertkit/convertkit-wordpress-libraries/.github" \
-x "vendor/convertkit/convertkit-wordpress-libraries/tests/*" \
-x "vendor/convertkit/convertkit-wordpress-libraries/composer.json" \
-x "vendor/autoload.php" \
-x "*.distignore" \
-x "*.env.*" \
-x ".gitignore" \
-x "*.md" \
-x "*.yml" \
-x "composer.json" \
-x "composer.lock" \
-x "*.xml" \
-x "*.neon" \
-x "*.dist" \
-x "*.example" \
-x "*.DS_Store" \

# Run composer to install dev dependencies, returning enviornment back to original state
composer update