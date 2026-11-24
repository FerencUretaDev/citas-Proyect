# Arquitectura — refactorización incremental

```text
Proyecto Citas/
├── index.php, panel.php, pacientes.php, doctores.php, sacar_cita.php, ...
│   (entradas HTTP compatibles)
├── controladores/      # 14 controladores: solicitudes, autorización y coordinación
├── servicios/          # autenticación, usuarios, DNI, catálogo, estados y reservas
├── repositorios/       # CitaDAO, DirectorioDAO, UsuarioDAO
├── modelos/            # Directorio (existente)
├── vistas/             # HTML y componentes (sidebar)
├── middlewares/        # sesión y perfil activo
├── config/             # conexión a MySQL
├── pruebas/            # pruebas unitarias de reglas sin MySQL
├── migraciones/        # scripts históricos sin cambios
├── css/ y js/          # recursos originales
└── modulo-reportes/   # código Java, pom.xml y Logback originales
```

## Flujo de reserva
`GET/POST sacar_cita` → `controladores/ReservaController.php` → `servicios/RegistroCitaService.php` → `repositorios/CitaDAO.php` → MySQL. La vista `vistas/sacar_cita.php` recibe los datos y mensajes del controlador. El servicio conserva la transacción y los bloqueos de doctor y paciente. Las restricciones se encuentran en `servicios/ReservaService.php`.

## Otros flujos
- `index.php` → AccesoController → AutenticacionService → UsuarioDAO.
- `consultar_dni` → DniController → DniService → API externa. Conserva autenticación, CSRF y token en el INI privado.
- `pacientes` y `doctores` → controlador correspondiente → DirectorioDAO para listas y consulta de usuario; UsuarioService para creación de cuentas. Las escrituras CRUD aún residen en los controladores.
- `estado_perfil` → EstadoPerfilController → modelo Directorio existente → vista estado_perfil.
- `exportar_excel_java.php` → ExportacionJavaController → JAR privado existente.

## Seguridad de rutas
Apache bloquea las rutas HTTP de las capas internas, archivos SQL, respaldos y diagnósticos; los includes PHP siguen funcionando localmente. Se aceptan las rutas públicas con o sin `.php` sin redirigir a rutas físicas del servidor. Esta protección presupone que Apache lee `.htaccess` (AllowOverride adecuado). Para producción, se recomienda un DocumentRoot dedicado a `public/`, HTTPS y una revisión integral de permisos, configuración y secretos.

## Siguientes fases
1. Completar DAO para CRUD, agenda, panel, reprogramación y reportes.
2. Centralizar todas las reglas de citas en cada operación de escritura.
3. Pruebas de integración y concurrencia MySQL; pruebas de interfaz y CI.
4. Normalizar especialidades y horarios mediante migraciones planificadas, no incluidas en esta actualización.

No se presenta esta fase como MVC/DAO completo, TDD retroactivo ni certificación SOLID.
