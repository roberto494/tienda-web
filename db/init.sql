-- Se ejecuta automáticamente SOLO la primera vez que se crea el volumen db_data
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario         VARCHAR(20)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    rol             ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso   DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(120)  NOT NULL,
    categoria       VARCHAR(30)   NOT NULL,
    descripcion     TEXT          NOT NULL,
    especificaciones TEXT         NOT NULL,
    precio          DECIMAL(8,2)  NOT NULL,
    precio_anterior DECIMAL(8,2)  NULL,
    stock           INT UNSIGNED  NOT NULL DEFAULT 0,
    imagen          VARCHAR(60)   NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carrito persistente: se conserva aunque el usuario cierre sesión
CREATE TABLE IF NOT EXISTS carrito (
    usuario_id  INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad    INT UNSIGNED NOT NULL,
    anadido_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, producto_id),
    CONSTRAINT fk_carrito_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    CONSTRAINT fk_carrito_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado      ENUM('pendiente','enviado','cancelado') NOT NULL DEFAULT 'pendiente',
    total       DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las líneas guardan nombre y precio del momento de la compra
CREATE TABLE IF NOT EXISTS pedido_lineas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id       INT UNSIGNED NOT NULL,
    producto_id     INT UNSIGNED NULL,
    nombre          VARCHAR(120) NOT NULL,
    imagen          VARCHAR(60)  NOT NULL,
    precio_unitario DECIMAL(8,2) NOT NULL,
    cantidad        INT UNSIGNED NOT NULL,
    CONSTRAINT fk_linea_pedido   FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_linea_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de eventos de seguridad (útil para la Sesión 4)
CREATE TABLE IF NOT EXISTS eventos (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo        VARCHAR(30)  NOT NULL,
    usuario     VARCHAR(120) NULL,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  VARCHAR(255) NULL,
    detalle     VARCHAR(255) NULL,
    INDEX idx_tipo_fecha (tipo, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO productos (nombre, categoria, descripcion, especificaciones, precio, precio_anterior, stock, imagen) VALUES
('Tarjeta gráfica Vortex X70 12 GB', 'graficas',
 'Gráfica de gama media-alta para jugar en 1440p con trazado de rayos y escalado por IA. Triple ventilador y backplate metálico.',
 '12 GB GDDR6X|Boost 2610 MHz|PCIe 4.0 x16|3x DisplayPort 1.4a, 1x HDMI 2.1|TDP 200 W',
 599.90, 679.90, 14, 'gpu.svg'),
('Procesador Arkon R7 8700 8 núcleos', 'procesadores',
 'Procesador de 8 núcleos y 16 hilos con gran caché L3, ideal para gaming y creación de contenido.',
 '8 núcleos / 16 hilos|Hasta 5,1 GHz|32 MB caché L3|Socket AM5|TDP 65 W',
 329.00, NULL, 23, 'cpu.svg'),
('Memoria RAM Pulse 32 GB (2x16) DDR5 6000', 'memoria',
 'Kit de doble canal DDR5 con disipador de aluminio y perfil bajo, compatible con perfiles de overclock automáticos.',
 '2 x 16 GB|DDR5 6000 MT/s|CL30|1,35 V|Disipador de aluminio',
 119.99, 139.99, 40, 'ram.svg'),
('SSD NVMe Flux 2 TB PCIe 4.0', 'almacenamiento',
 'Unidad M.2 2280 de alta velocidad con disipador incluido, para sistema operativo y juegos.',
 '2 TB|Lectura 7000 MB/s|Escritura 6500 MB/s|M.2 2280 NVMe|Disipador incluido',
 149.90, NULL, 31, 'ssd.svg'),
('Teclado mecánico Kestrel TKL', 'perifericos',
 'Teclado compacto sin bloque numérico, switches lineales intercambiables en caliente e iluminación RGB por tecla.',
 'Formato TKL (87 teclas)|Switches lineales hot-swap|RGB por tecla|USB-C desmontable|Teclas PBT',
 89.95, 109.95, 18, 'teclado.svg'),
('Monitor Horizon 27" QHD 165 Hz', 'monitores',
 'Monitor IPS de 27 pulgadas con resolución 2560x1440, 165 Hz y 1 ms, con sincronización adaptativa.',
 '27" IPS|2560 x 1440|165 Hz / 1 ms|HDR400|Altura e inclinación ajustables',
 279.00, 319.00, 9, 'monitor.svg');
