# Reportes Excel del Sistema de Citas

Extrae la carpeta `modulo-reportes` dentro de `C:\xampp\htdocs\Proyecto Citas`.
Abre PowerShell en Visual Studio Code y ejecuta:

```powershell
Set-Location "C:\xampp\htdocs\Proyecto Citas\modulo-reportes" -ErrorAction Stop
mvn clean package
if ($LASTEXITCODE -ne 0) { throw "Falló la compilación de Maven." }
Copy-Item ".\target\modulo-reportes.jar" "C:\xampp\private\java\modulo-reportes.jar" -ErrorAction Stop
```

El JAR incluye sus dependencias. PHP le pasa la ruta del Excel y los filtros de
fecha inicial, fecha final, DNI y estado, en ese orden. Cada filtro vacío
equivale a omitirlo. El rango de fechas incluye ambos extremos.

Si la contraseña de MySQL es vacía en tu instalación local, quita únicamente
`|| $config["DB_PASSWORD"] === ""` de la validación de
`exportar_excel_java.php`. Conserva `!isset($config["DB_PASSWORD"])`.
El archivo `reportes.ini` permanece en `C:\xampp\private\config`.

Entra al panel de administrador y exporta un reporte con los cuatro filtros
vacíos. Con los datos de la consulta verificada debería contener dos citas.
Prueba también con el estado `pendiente` y un rango del 05/10/2026 al
05/10/2026. Si la descarga falla, revisa las últimas líneas del registro:

```powershell
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
```

## Bibliotecas del avance

- Google Guava define los encabezados en una lista inmutable.
- Apache POI genera el Excel. Los DNI se escriben como texto.
- Apache Commons IO prepara la carpeta de salida.
- Logback registra el resultado y los errores mediante SLF4J.
- MySQL Connector/J conecta Java a la base de datos.
