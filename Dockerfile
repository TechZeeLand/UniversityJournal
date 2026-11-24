# University Journal - single image: nginx + PHP-FPM + MariaDB (managed by supervisord)
FROM alpine:3.20

RUN apk add --no-cache \
      nginx supervisor mariadb mariadb-client tzdata curl \
      php83 php83-fpm php83-pdo_mysql php83-mysqlnd php83-mbstring php83-intl php83-gd \
      php83-exif php83-xml php83-dom php83-session php83-ctype php83-fileinfo php83-openssl \
      php83-curl php83-zip php83-opcache php83-tokenizer php83-simplexml php83-xmlwriter \
 && ln -sf /usr/bin/php83 /usr/bin/php \
 && mkdir -p /run/mysqld /var/www/storage /var/lib/nginx/tmp \
 && chown -R mysql:mysql /run/mysqld

COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/php-fpm.conf      /etc/php83/php-fpm.d/www.conf
COPY docker/php.ini           /etc/php83/conf.d/99-app.ini
COPY docker/mariadb.cnf       /etc/my.cnf.d/zz-app.cnf
COPY docker/supervisord.conf  /etc/supervisord.conf
COPY docker/entrypoint.sh     /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/app
COPY app        ./app
COPY bin        ./bin
COPY migrations ./migrations
COPY public     ./public

VOLUME ["/var/lib/mysql", "/var/www/storage"]
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD curl -fsS http://127.0.0.1/health || exit 1
ENTRYPOINT ["/entrypoint.sh"]
