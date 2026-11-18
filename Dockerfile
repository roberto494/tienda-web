# Imagen oficial de PHP con Apache, versión fijada
FROM php:8.3-apache

# Extensión para conectar con MySQL mediante PDO
RUN docker-php-ext-install pdo_mysql

# Módulos de Apache: URLs limpias (/tienda) y cabeceras de seguridad
RUN a2enmod rewrite headers

# Configuración propia de PHP y Apache
COPY docker/php-security.ini /usr/local/etc/php/conf.d/zz-security.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-security.conf /etc/apache2/conf-available/security-extra.conf
RUN a2enconf security-extra

# Código de la aplicación. Solo src/public es accesible desde el navegador
COPY src/ /var/www/app/
RUN mkdir -p /var/www/app/logs \
    && chown -R www-data:www-data /var/www/app/logs \
    && chmod -R a-w /var/www/app/public /var/www/app/includes /var/www/app/scripts

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/nexobit-entrypoint

EXPOSE 80
ENTRYPOINT ["nexobit-entrypoint"]

# Docker comprueba cada 30 s que la aplicación responde
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/health') === false ? 1 : 0);"
