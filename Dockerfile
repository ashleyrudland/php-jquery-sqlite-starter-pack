FROM php:8.2-apache

# Install SQLite and enable PHP SQLite extension
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Apache modules
RUN a2enmod rewrite proxy proxy_http

# Install Python and pip
RUN apt-get update && apt-get install -y python3 python3-pip python3-venv

# Create a virtual environment and install SQLite Web
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"
RUN pip install sqlite-web

# Create a symlink to make sqlite_web accessible
RUN ln -s /opt/venv/bin/sqlite_web /usr/local/bin/sqlite_web

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html /opt/venv

# Create data directory, SQLite database file, and set permissions
RUN mkdir -p /data && \
	touch /data/database.sqlite && \
	chown -R www-data:www-data /data && \
	chmod 777 /data && \
	chmod 666 /data/database.sqlite

# Configure PHP error logging
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini && \
	echo "display_errors = Off" >> /usr/local/etc/php/php.ini && \
	echo "log_errors = On" >> /usr/local/etc/php/php.ini && \
	echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini

# Configure Apache to handle PHP errors gracefully
RUN echo "php_flag log_errors on" >> /etc/apache2/apache2.conf && \
	echo "php_value error_reporting 32767" >> /etc/apache2/apache2.conf && \
	echo "php_flag display_errors off" >> /etc/apache2/apache2.conf

# Copy Apache configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Start SQLite Web and Apache
CMD ["/start.sh"]