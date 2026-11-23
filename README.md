# PA4 – Marah: API REST con arquitectura MVC

API REST en PHP + MySQL para **Marah**, emprendimiento de venta de kéfir. Usa la base de datos `marah_db` de la PA3 y expone **un módulo CRUD por cada tabla**.

- **Curso:** Programación Web Básica · ISIL · NRC 3657 · Periodo 2026-20
- **Docente:** Luiggui Enrique Ynga Becerra
- **Alumno:** Adrián Moisés Basurco Castro
- **Proyecto base del profesor:** https://github.com/leyngab-isil/3657_PAGINA_WEB

## Módulos (uno por tabla)

| N.º | Tabla | Ruta base | Operaciones |
|---|---|---|---|
| 1 | `categorias` | `/api/categorias` | GET, GET por ID, POST, PUT, DELETE (lógico) |
| 2 | `productos` | `/api/productos` | GET, GET por ID, POST, PUT, DELETE (lógico) |
| 3 | `clientes` | `/api/clientes` | GET, GET por ID, POST, PUT, DELETE |
| 4 | `pedidos` | `/api/pedidos` | GET, GET por ID (con detalles), POST, PUT, DELETE |
| 5 | `detalle_pedido` | `/api/detalles` | GET, GET por ID, POST, PUT, DELETE |
| 6 | `contactos` | `/api/contactos` | GET, GET por ID, POST, PUT, DELETE |

## Requisitos

- **XAMPP** con PHP 8.1 o superior (Apache con `mod_rewrite`)
- **MySQL 8.0**: el de XAMPP o MySQL Server, con **MySQL Workbench** como cliente
- **Postman**, para probar los endpoints
- Composer (opcional): si no se ejecuta `composer install`, el proyecto usa su propio autoload PSR-4

## Instalación

1. **Copiar el proyecto** en `C:\xampp\htdocs\3657_Basurco_Adrian_API_MVC`, o clonarlo:
   ```bash
   cd C:\xampp\htdocs
   git clone <URL_DEL_REPOSITORIO> 3657_Basurco_Adrian_API_MVC
   ```

2. **Crear la base de datos:** abrir `database/PA4_Marah_BD.sql` en MySQL Workbench y ejecutarlo completo (rayo ⚡).
   ⚠️ El script elimina `marah_db` si ya existe y la crea de nuevo con los datos de prueba.

3. **Configurar la conexión:** copiar `.env.example` como `.env` y completar los datos:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=marah_db
   DB_USER=root
   DB_PASS=tu_contraseña
   ```
   El archivo `.env` está en `.gitignore`, así que nunca se sube a GitHub.

4. **Encender Apache** en el XAMPP Control Panel. También MySQL, si usas el de XAMPP.

5. **Probar:** abrir en el navegador
   `http://localhost/3657_Basurco_Adrian_API_MVC/public/api`
   Debe responder `"API funcionando correctamente"`.

**Alternativa sin Apache:** desde la carpeta del proyecto ejecutar
`php -S localhost:8000 -t public` y usar `http://localhost:8000/api`.

## Pruebas con Postman

1. En Postman: **Import** → `postman/PA4_Marah_API.postman_collection.json`.
2. La variable `base_url` de la colección apunta a `http://localhost/3657_Basurco_Adrian_API_MVC/public/api`. Cámbiala si usas otra ruta.
3. Ejecutar las peticiones **en orden**, o usar **Run collection**, justo después de cargar el script SQL. La colección:
   - Tiene 61 peticiones: los casos correctos y los de error de cada módulo.
   - Guarda automáticamente los IDs creados (`id_producto_nuevo`, etc.) para usarlos en GET/PUT/DELETE.
   - Verifica en cada petición el código HTTP esperado.

## Endpoints

Base: `http://localhost/3657_Basurco_Adrian_API_MVC/public/api`

### Categorías
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/categorias` | Lista las categorías activas con su cantidad de productos |
| GET | `/categorias/{id}` | Obtiene una categoría |
| POST | `/categorias` | Crea: `{ "nombre", "descripcion" }` |
| PUT | `/categorias/{id}` | Actualiza nombre y descripción |
| DELETE | `/categorias/{id}` | Desactiva (no se permite si tiene productos activos) |

### Productos
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/productos` | Lista productos activos (filtro opcional `?categoria=2`) |
| GET | `/productos/{id}` | Obtiene un producto con su categoría |
| POST | `/productos` | Crea: `{ "id_categoria", "nombre", "descripcion", "presentacion", "precio", "stock" }` |
| PUT | `/productos/{id}` | Actualiza todos los datos |
| DELETE | `/productos/{id}` | Desactiva (no se permite si está en pedidos en curso) |

### Clientes
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/clientes` | Lista clientes con su total de pedidos |
| GET | `/clientes/{id}` | Obtiene un cliente |
| POST | `/clientes` | Crea: `{ "nombres", "apellidos", "telefono", "correo", "direccion" }` |
| PUT | `/clientes/{id}` | Actualiza todos los datos |
| DELETE | `/clientes/{id}` | Elimina (no se permite si tiene pedidos) |

### Pedidos
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/pedidos` | Lista pedidos con el cliente (filtro opcional `?estado=Pendiente`) |
| GET | `/pedidos/{id}` | Obtiene el pedido **con sus detalles** |
| POST | `/pedidos` | Crea: `{ "id_cliente", "direccion_entrega", "observaciones", "detalles": [{ "id_producto", "cantidad" }] }` |
| PUT | `/pedidos/{id}` | Actualiza `direccion_entrega`, `estado` y `observaciones` |
| DELETE | `/pedidos/{id}` | Elimina (solo Pendiente o Cancelado) |

### Detalle de pedido
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/detalles` | Lista todos los detalles |
| GET | `/detalles/{id}` | Obtiene un detalle |
| GET | `/pedidos/{id}/detalles` | Lista los detalles de un pedido |
| POST | `/detalles` | Agrega un producto: `{ "id_pedido", "id_producto", "cantidad" }` |
| PUT | `/detalles/{id}` | Cambia la cantidad: `{ "cantidad" }` |
| DELETE | `/detalles/{id}` | Quita el producto del pedido |

### Contactos
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/contactos` | Lista mensajes (filtro opcional `?estado=Nuevo`) |
| GET | `/contactos/{id}` | Obtiene un mensaje |
| POST | `/contactos` | Crea: `{ "nombre", "correo", "telefono", "asunto", "mensaje" }` |
| PUT | `/contactos/{id}` | Actualiza los datos y el `estado` (Nuevo, En revision, Atendido) |
| DELETE | `/contactos/{id}` | Elimina |

## Reglas de negocio y relaciones

- **Claves foráneas:** antes de guardar se verifica que existan la categoría (producto), el cliente (pedido), y el pedido y el producto (detalle).
- **ON DELETE RESTRICT:** no se elimina una categoría con productos activos ni un cliente con pedidos. En esos casos la API responde 409.
- **UNIQUE:** no se repiten el nombre de categoría, ni el teléfono o correo de cliente, ni el mismo producto dentro de un pedido. Si pasa, la API responde 409.
- **Precio, subtotal y total:** el precio unitario se toma del producto y el subtotal es cantidad × precio. El total del pedido se recalcula automáticamente con la suma de los subtotales; el cliente no lo envía.
- **Stock:**
  - Al agregar productos a un pedido se descuenta el stock; al quitarlos, cancelar o eliminar el pedido, se devuelve.
  - Estas operaciones usan **transacciones**: si un producto no tiene stock suficiente, no se guarda nada (ROLLBACK).
- **Estados del pedido:**
  - El detalle solo se modifica si el pedido está en estado Pendiente o Confirmado.
  - Los pedidos Entregados o Cancelados ya no se pueden modificar.

## Formato de respuesta y códigos HTTP

```json
{ "success": true, "message": "Producto creado correctamente", "data": { ... } }
```
```json
{ "success": false, "message": "Datos inválidos", "errors": { "precio": "El campo precio debe ser mayor que 0" } }
```

| Código | Cuándo |
|---|---|
| 200 | Consulta, actualización o eliminación correcta |
| 201 | Registro creado (POST) |
| 400 | Datos inválidos, campos obligatorios faltantes, JSON mal escrito o clave foránea inexistente |
| 404 | Registro o ruta no encontrados |
| 405 | Método HTTP no permitido en una ruta existente |
| 409 | Conflicto: duplicado, registro relacionado, stock insuficiente o estado que no permite el cambio |
| 500 | Error interno o falla de conexión con la base de datos (sin mostrar detalles sensibles) |

## Estructura del proyecto

```
3657_Basurco_Adrian_API_MVC/
├── app/
│   ├── Controllers/     # Reciben la petición, validan y arman la respuesta
│   ├── Models/          # Consultas SQL (PDO) y transacciones
│   └── Middleware/      # CORS
├── config/
│   ├── Env.php          # Lee las variables del archivo .env
│   └── Database.php     # Conexión PDO a MySQL
├── core/
│   ├── Router.php       # Rutas por método HTTP (basado en el proyecto del docente)
│   ├── Request.php      # Lee y valida el JSON recibido
│   ├── Response.php     # Respuestas JSON uniformes
│   ├── Validator.php    # Reglas de validación
│   └── HttpException.php
├── routes/api.php       # Definición de los endpoints
├── public/              # Raíz web: index.php (punto de entrada) y .htaccess
├── database/PA4_Marah_BD.sql
├── postman/PA4_Marah_API.postman_collection.json
├── .env.example         # Plantilla de la conexión (el .env real no se sube)
└── composer.json
```

## Problemas comunes

- **"Ruta no encontrada" en todo:** en `C:\xampp\apache\conf\httpd.conf` debe estar `AllowOverride All` para `htdocs`, y `mod_rewrite` debe estar activo.
- **"Error al conectar con la base de datos":**
  - Revisa que exista el archivo `.env` con tus datos.
  - Revisa que MySQL esté encendido.
  - Revisa que se haya ejecutado el script SQL.
- **MySQL de XAMPP no inicia:** el puerto 3306 está ocupado por MySQL Server (servicio MySQL80). Usa uno solo de los dos.
- **"El cuerpo de la petición no es un JSON válido":** en Postman usa Body → raw → JSON y revisa que no haya comas sobrantes.
