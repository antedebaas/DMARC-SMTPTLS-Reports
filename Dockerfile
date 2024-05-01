FROM alpine:latest

LABEL Maintainer="Ante de Baas @antedebaas on GitHub>" \
      Description="DMARC & SMTP-TLS Reports processor and visualizer"

EXPOSE 8080

ENV DATABASE_TYPE=mysql
ENV DATABASE_HOST=
ENV DATABASE_PORT=3306
ENV DATABASE_NAME=
ENV DATABASE_USER=
ENV DATABASE_PASSWORD=
ENV MAILER_HOST=
ENV MAILER_SMTPPORT=25
ENV MAILER_IMAPPORT=993
ENV MAILER_USER=
ENV MAILER_PASSWORD=
ENV DELETE_PROCESSED_MAILS=false
ENV ENABLE_REGISTRATION=true
ENV MAILCHECK_SCHEDULE="0 * * * *"
ENV TZ=UTC

RUN apk --update add ca-certificates && \
    apk --no-cache add \
        curl \
        nginx \
        php83 \
        php83-ctype \
        php83-dom \
        php83-fileinfo \
        php83-fpm \
        php83-iconv \
        php83-imap \
        php83-mbstring \
        php83-pdo \
        php83-pdo_mysql \
        php83-pdo_pgsql \
        php83-pdo_sqlite \
        php83-phar \
        php83-session \
        php83-simplexml \
        php83-tokenizer \
        php83-xml \
        php83-xmlwriter \
        php83-zip \
        supervisor \
        tzdata

COPY dockerfiles/ /

RUN chmod +x /usr/local/bin/containerstartup.sh && \
    dos2unix /usr/local/bin/containerstartup.sh && \
    chmod +x /usr/local/bin/phpstartup.sh && \
    dos2unix /usr/local/bin/phpstartup.sh && \
    chmod +x /usr/local/bin/checkmail.sh && \
    dos2unix /etc/nginx/nginx.conf && \
    dos2unix /etc/php83/php-fpm.d/www.conf && \
    dos2unix /etc/php83/conf.d/custom.ini && \
    dos2unix /etc/supervisor/conf.d/supervisord.conf && \
    mkdir -p /var/www/html && \
    ln -s /usr/bin/php83 /usr/bin/php && \
    ln -s /usr/sbin/php-fpm83 /usr/sbin/php-fpm

WORKDIR /var/www/html
COPY --chown=nobody . /var/www/html/
RUN wget https://getcomposer.org/composer-stable.phar -O /usr/local/bin/composer && chmod +x /usr/local/bin/composer && \
    /usr/local/bin/composer install

RUN chown -R nobody.nobody /var/www/html && \
  chown -R nobody.nobody /run && \
  chown -R nobody.nobody /var/lib/nginx && \
  chown -R nobody.nobody /var/log/nginx

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping