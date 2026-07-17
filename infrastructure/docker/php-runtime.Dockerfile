FROM php:8.3-cli-alpine

RUN apk add --no-cache curl-dev \
    && docker-php-ext-install curl mysqli pdo pdo_mysql

WORKDIR /workspace

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD wget -q -O - http://127.0.0.1:8080/health || exit 1

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/workspace/public"]
