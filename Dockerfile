FROM php
RUN docker-php-ext-install sockets
RUN docker-php-ext-install pcntl
