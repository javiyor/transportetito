# TransporteTito (MVP Facturacion ARCA)

Este repo prepara el MVP para emitir Factura Electronica ARCA (WSFE) con PV 0002, operacion minima (manifiestos/pedidos) y cuenta corriente basica.

Stack objetivo (VPS):
- PHP 8.3 + Laravel 11
- PostgreSQL 16, Redis
- Nginx
- Docker Compose
- Storage S3 compatible (MinIO en VPS)

## Arranque rapido (en VPS con Docker)

1) Copiar `.env.example` a `.env` y ajustar claves/hostnames.
2) Levantar servicios:

```bash
docker compose up -d --build
```

3) Instalar dependencias (dentro del contenedor app):

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
```

4) Abrir en navegador:
- En VPS: https://transportetito.5amsoftware.com.ar (Caddy maneja HTTPS)
- En local (sin DNS): podes cambiar el Caddyfile o comentar el servicio caddy y exponer nginx.

## Estado

Este repo inicia la estructura (infra + esquema de datos + modulos base). La emision real ARCA requiere cargar cert/key (homologacion/produccion) desde la UI y configurar WSAA/WSFE.
