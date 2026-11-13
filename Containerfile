FROM docker.io/library/php:8.4-apache
WORKDIR /var/www
RUN docker-php-ext-install pdo_mysql
COPY app/ /var/www/
COPY baza.sql /var/www/baza.sql
COPY LICENSE licensing.md LICENSE_AUDIT.md /usr/share/doc/arkusz/
COPY scripts/container-entrypoint.sh /usr/local/bin/arkusz-entrypoint
RUN chmod +x /usr/local/bin/arkusz-entrypoint
RUN chown -R www-data:www-data /var/www \
    && mkdir -p /var/lib/php/sessions \
    && chown www-data:www-data /var/lib/php/sessions \
    && chmod 700 /var/lib/php/sessions \
    && echo 'session.save_path=/var/lib/php/sessions' > /usr/local/etc/php/conf.d/arkusz-sessions.ini
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN sed -ri 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf
LABEL org.opencontainers.image.title="Arkusz" org.opencontainers.image.licenses="AGPL-3.0-only" org.opencontainers.image.version="1.4.0"
EXPOSE 80

ENTRYPOINT ["arkusz-entrypoint"]
CMD ["apache2-foreground"]
