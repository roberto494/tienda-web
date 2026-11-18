# NexoBit · Tienda de informática (laboratorio PPS)

Tienda web ficticia de componentes informáticos creada para la práctica de
**Puesta en Producción Segura**. Stack: **PHP 8.3 + Apache**, **MySQL 8.4**, **CSS**, desplegado con **Docker Compose**.

> ⚠️ Material educativo. Usa datos y cuentas ficticios. No expongas el contenedor a Internet.

## Funcionalidades

**Sin sesión** no se puede acceder a nada: cualquier ruta privada (`/tienda`, `/carrito`, `/admin/…`) redirige a `/login`. Desde el login se inicia sesión o se crea una cuenta.

| Rol **usuario** (cliente) | Rol **admin** |
|---|---|
| Ver catálogo, buscar y filtrar por categoría | Panel con estadísticas, stock bajo y actividad de seguridad |
| Carrito **guardado en la base de datos**: sigue igual al cerrar sesión y volver a entrar | **Usuarios**: crear, modificar (nombre, email, rol, contraseña) y eliminar |
| Tramitar pedido (compra simulada, descuenta stock) | **Productos**: crear, modificar y eliminar (precio, stock, imagen, specs…) |
| **Mis pedidos**: ver sus pedidos y **cancelar** los que están pendientes (repone stock) | **Pedidos**: ver todos, marcar como enviado o cancelar |

- Las cuentas creadas desde el registro siempre tienen el rol `usuario`.
- El admin no tiene carrito. Si un usuario intenta entrar en `/admin/…`, recibe un **403** y queda registrado.
- Un pedido ya enviado no se puede cancelar.
- Si el admin cambia el rol de alguien o lo elimina, el cambio se aplica **al instante**, aunque esa persona tenga la sesión abierta.

---

## Estructura

```
nexobit/
├── Dockerfile               # php:8.3-apache + pdo_mysql
├── compose.yaml             # Servicios web + db, volúmenes y red
├── .env.example             # Plantilla de variables (sin secretos reales)
├── .gitignore / .dockerignore
├── db/init.sql              # Tablas y productos iniciales (solo primer arranque)
├── docker/
│   ├── entrypoint.sh        # Crea el admin desde .env y arranca Apache
│   ├── php-security.ini     # Ajustes de PHP y cookies de sesión
│   ├── apache-vhost.conf    # DocumentRoot = public/, URLs limpias
│   └── apache-security.conf # Cabeceras de seguridad, ocultar versión
└── src/
    ├── includes/            # Lógica NO accesible desde el navegador
    │   ├── db.php           # Conexión PDO
    │   ├── session.php      # Sesión segura, usuario actual, CSRF, utilidades
    │   ├── auth.php         # Exige sesión y rol (requiere_rol)
    │   ├── pedidos.php      # Cancelación de pedidos y reposición de stock
    │   ├── logger.php       # Registro de eventos de seguridad
    │   └── layout.php       # Cabecera y pie HTML según rol
    ├── scripts/
    │   └── crear_admin.php  # Solo CLI: crea el administrador
    └── public/              # Lo único que sirve Apache
        ├── login.php  registro.php  logout.php  index.php
        ├── tienda.php  producto.php  carrito.php  pedidos.php
        ├── admin/ index.php  usuarios.php  productos.php  pedidos.php
        ├── health.php  404.php
        └── assets/ (css, js, img)
```

### Base de datos

| Tabla | Contenido |
|---|---|
| `usuarios` | usuario, email, `password_hash` (bcrypt), `rol` (`usuario`/`admin`) |
| `productos` | nombre, categoría, descripción, especificaciones, precio, stock, imagen |
| `carrito` | (usuario, producto, cantidad). Persistente entre sesiones |
| `pedidos` | usuario, fecha, estado (`pendiente`/`enviado`/`cancelado`), total |
| `pedido_lineas` | productos de cada pedido, con nombre y precio **del momento de la compra** |
| `eventos` | registro de seguridad: fecha, tipo, usuario, IP, user-agent, detalle |

Las claves foráneas mantienen la coherencia:
- Si se borra un usuario, se borran su carrito y sus pedidos.
- Si se borra un producto, desaparece de los carritos, pero los pedidos antiguos lo conservan.

## Arquitectura

```
 Navegador (tu PC)
      │  http://localhost:8080
      ▼
 127.0.0.1:8080  ── solo escucha en tu equipo, no en la red
      │
┌─────┴──────────── red Docker "backend" ─────────────────┐
│  nexobit-web (PHP + Apache, puerto 80)                  │
│     │  PDO (usuario de aplicación, no root)             │
│     ▼                                                   │
│  nexobit-db (MySQL 8.4, puerto 3306 NO publicado)       │
└─────────────────────────────────────────────────────────┘
   volumen db_data  → /var/lib/mysql      (usuarios, productos, carritos, pedidos, eventos)
   volumen app_logs → /var/www/app/logs   (seguridad.log)
```

---

## Puesta en marcha (Sesión 1)

```bash
# 1. Crear el archivo de variables a partir de la plantilla
cp .env.example .env

# 2. Generar contraseñas propias y pegarlas en .env
python3 -c "import secrets; print(secrets.token_urlsafe(32))"   # MYSQL_ROOT_PASSWORD
python3 -c "import secrets; print(secrets.token_urlsafe(32))"   # MYSQL_PASSWORD
python3 -c "import secrets; print(secrets.token_urlsafe(16))"   # ADMIN_PASSWORD (mín. 12)
nano .env

# 3. Construir y levantar
docker compose up --build -d

# 4. Comprobar estado (ambos deben salir "healthy")
docker compose ps

# 5. Endpoint de salud
curl -fsS http://localhost:8080/health
# {"status":"ok","database":"ok"}

# 6. Registros (debe aparecer "[ADMIN] Administrador 'admin' creado.")
docker compose logs --tail=30 web
docker compose logs --tail=30 db
```

Abre **http://localhost:8080**:
- Entra con el usuario `ADMIN_USER` y la contraseña `ADMIN_PASSWORD` del `.env` para acceder al panel.
- Crea una cuenta desde «Crea una gratis» para probar como cliente.

Para detenerlo: `docker compose down`.

> **Importante:** `db/init.sql` solo se ejecuta cuando el volumen `db_data` está vacío. Si ya habías levantado una versión anterior, o cambias el esquema, reinicia los datos **una vez** con `docker compose down -v` y vuelve a hacer `docker compose up --build -d`. Ojo: `-v` borra todos los datos.

### Comprobar la redirección al login

```bash
curl -i http://localhost:8080/tienda
# HTTP/1.1 303 See Other
# Location: /login
```

### Comprobar la persistencia de datos

Antes de empezar, crea una cuenta, añade algo al carrito y haz un pedido.

```bash
# Atajo para consultar MySQL dentro del contenedor
sql() { docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -t' <<< "$1"; }

sql "SELECT id, usuario, rol, creado_en FROM usuarios;"
sql "SELECT * FROM carrito;"
sql "SELECT id, usuario_id, estado, total FROM pedidos;"

docker compose restart                       # reinicio
docker compose down && docker compose up -d  # destruir y recrear contenedores

sql "SELECT id, usuario, rol FROM usuarios;" # los datos siguen ahí (volumen db_data)
```

### Ver los eventos de seguridad

```bash
docker compose logs web | grep SEGURIDAD
docker compose exec web tail -n 20 /var/www/app/logs/seguridad.log
sql "SELECT fecha, tipo, usuario, ip, detalle FROM eventos ORDER BY id DESC LIMIT 20;"
```

Tipos de evento:
- **Acceso:** `REGISTRO`, `LOGIN_OK`, `LOGIN_FALLIDO`, `LOGIN_BLOQUEADO`, `LOGOUT`, `SESSION_TIMEOUT`.
- **Accesos denegados:** `ACCESO_DENEGADO` (sin sesión), `ACCESO_PROHIBIDO` (rol incorrecto), `CSRF_INVALIDO`.
- **Pedidos:** `PEDIDO_CREADO`, `PEDIDO_CANCELADO`, `CANCELACION_RECHAZADA`.
- **Administración:** `ADMIN_USUARIO_CREADO`, `ADMIN_USUARIO_EDITADO` y `ADMIN_USUARIO_ELIMINADO`; los equivalentes `ADMIN_PRODUCTO_*`; `ADMIN_PEDIDO_ENVIADO` y `ADMIN_PEDIDO_CANCELADO`.

---

## Explicación de los archivos clave

| Archivo | Función |
|---|---|
| `Dockerfile` | Parte de `php:8.3-apache`, instala `pdo_mysql`, activa `rewrite` y `headers` y copia la configuración y el código. El `HEALTHCHECK` llama a `/health`. |
| `docker/entrypoint.sh` | Al arrancar crea la cuenta admin con los datos de `.env`, solo si no existe, y lanza Apache. |
| `compose.yaml` | Define `web` y `db`. Publica solo `127.0.0.1:8080` y la BD no tiene `ports`. `depends_on: service_healthy` hace que la web espere a MySQL. |
| Volúmenes | `db_data` guarda la base de datos y `app_logs` el log de seguridad. Sobreviven a `restart` y a `down`. |
| `127.0.0.1:8080` | El puerto solo se abre en la interfaz local: otro equipo de la red no puede conectarse. |
| `.env.example` | Indica qué variables hacen falta, sin valores reales. El `.env` real está en `.gitignore`. |
| `db/init.sql` | MySQL lo ejecuta solo la primera vez que el volumen está vacío. |

## Medidas de seguridad implementadas

- **Control de acceso por rol en el servidor** (`requiere_rol()`): ocultar botones no basta, cada página y cada POST lo comprueba.
- **El rol se lee de la BD en cada petición**, no se guarda en la sesión, así que no se puede manipular.
- **Sin escalada de privilegios desde el registro:** el `INSERT` no acepta el campo `rol`.
- **Protección IDOR:** un usuario solo ve y cancela sus propios pedidos (`WHERE usuario_id = ?`).
- **Salvaguardas del admin:** no puede borrarse ni quitarse el rol a sí mismo, y siempre queda al menos un admin.
- **Integridad de pedidos:**
  - La compra se hace en una transacción, con descuento de stock atómico (`stock >= ?`).
  - Los precios se toman de la BD, nunca del formulario.
- **Admin desde `.env`:** no hay contraseñas por defecto en el código ni en `init.sql`.
- **Imágenes de producto de una lista blanca** de archivos existentes. La subida de archivos está desactivada.
- **Contraseñas** con `password_hash()` (bcrypt). Nunca se guardan ni se registran en claro.
- **Consultas SQL:** PDO con sentencias preparadas en todas.
- **Salida HTML:** se escapa con `htmlspecialchars()`.
- **CSRF:** token en todos los formularios POST, incluido el logout.
- **Sesión:**
  - `session_regenerate_id(true)` al iniciar sesión.
  - Cookie `HttpOnly` + `SameSite=Strict`.
  - Caduca tras 30 min de inactividad.
- **Login:**
  - Mensaje genérico ante cualquier fallo.
  - Bloqueo tras 5 fallos por IP en 15 min.
- **Aislamiento:** `DocumentRoot` en `public/`, así que `includes/`, `scripts/` y `logs/` no son accesibles por URL.
- **Cabeceras de seguridad:**
  - CSP sin JavaScript en línea.
  - X-Frame-Options, nosniff y Referrer-Policy.
  - Versiones de PHP y Apache ocultas.
- **Errores:** `display_errors = Off`, se registran en el log del contenedor.
- **Base de datos:** no está publicada fuera de Docker, y la app usa un usuario MySQL propio, no root.

## Limitaciones conocidas (prototipo)

- Sin HTTPS: la cookie de sesión no lleva `Secure` en local.
- El bloqueo de login es por IP: se elude cambiando de IP y no bloquea por cuenta.
- No hay recuperación de contraseña ni verificación de email.
- El admin puede ver y borrar todo sin una segunda confirmación (sin 2FA ni aprobación doble).
- Borrar un usuario borra también sus pedidos: en un entorno real se anonimizaría.
- Las imágenes base no se escanean en busca de vulnerabilidades.

## Créditos de imágenes

Las ilustraciones de producto (`src/public/assets/img/*.svg`) son **originales, creadas para este proyecto**. Las marcas que aparecen (Vortex, Arkon, Pulse, Flux, Kestrel, Horizon, NexoBit) son ficticias.
