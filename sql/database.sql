/* Uso de ENUM para roles de usuario: Impide a nivel de base de datos la insercion de roles no contemplados por la logica de negocio 
evitando inconsistencias si una consulta se ejecutara fuera de la API.
Los roles de este sistema son fijos y corresponden a un modelo de permisos estatico (admin, encargado, guardia). 
Introducir una tabla auxiliar de roles añadiria complejidad innecesaria (claves foraneas, migraciones adicionales y JOINS en 
cada consulta de autenticacion).
*/



CREATE TABLE IF NOT EXISTS usuarios (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50)  NOT NULL UNIQUE,
  hash    VARCHAR(255) NOT NULL,                       -- password_hash()
  rol     ENUM('admin','encargado','guardia') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internos (                
  id               INT AUTO_INCREMENT PRIMARY KEY,
  legajo           VARCHAR(20) NOT NULL UNIQUE,
  apellido_paterno VARCHAR(60) NOT NULL,
  apellido_materno VARCHAR(60) NOT NULL,
  nombres          VARCHAR(60) NOT NULL,
  piso             VARCHAR(30) NOT NULL,
  celda            INT     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimientos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  interno_id   INT         NOT NULL,
  actividad    VARCHAR(30) NOT NULL,
  subactividad VARCHAR(40) NOT NULL,
  salida       DATETIME    NOT NULL,
  regreso      DATETIME    NULL,                       -- NULL = sigue afuera
  usuario      VARCHAR(50) NOT NULL,
  FOREIGN KEY (interno_id) REFERENCES internos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- El usuario "admin" se crea solo en el primer login usando la variable ADMIN_PASSWORD.

CREATE TABLE IF NOT EXISTS historial (               -- altas y bajas (se conserva aunque se borre el interno)
  id       INT AUTO_INCREMENT PRIMARY KEY,
  legajo   VARCHAR(20)  NOT NULL,
  interno  VARCHAR(150) NOT NULL,                    -- nombre al momento del hecho
  tipo     ENUM('alta','baja') NOT NULL,
  fecha    DATETIME     NOT NULL,
  detalles VARCHAR(200) NULL,
  usuario  VARCHAR(50)  NOT NULL,
  INDEX (legajo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cree una nueva tabla para hacer un registro de movimientos de e/s de las actividades
CREATE TABLE IF NOT EXISTS registro_actividades (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  interno_id   INT         NOT NULL,
  legajo       VARCHAR(20) NOT NULL,
  nombre       VARCHAR(150) NOT NULL,
  accion       ENUM('salida','regreso') NOT NULL,
  actividad    VARCHAR(30) NOT NULL,
  subactividad VARCHAR(40) NULL,
  fecha        DATETIME    NOT NULL,
  usuario      VARCHAR(50)  NOT NULL,
  INDEX (legajo),
  INDEX (fecha),
  FOREIGN KEY (interno_id) REFERENCES internos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;