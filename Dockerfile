# Choose our base image
FROM serversideup/php:8.4-fpm-nginx

# Switch to root so we can do root things
USER root

# Install extensions with root permissions
RUN install-php-extensions gd intl

# Drop back to our unprivileged user
USER www-data
