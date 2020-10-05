FROM php:cli

RUN curl https://getcomposer.org/composer-stable.phar --output /usr/bin/composer \
	&& chmod +X /usr/bin/composer && chmod 766 /usr/bin/composer

WORKDIR /project