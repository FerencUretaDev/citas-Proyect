package pe.citas;

import com.google.common.collect.ImmutableList;
import org.apache.commons.io.FileUtils;
import org.apache.poi.ss.usermodel.Row;
import org.apache.poi.ss.usermodel.Sheet;
import org.apache.poi.xssf.streaming.SXSSFWorkbook;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.OutputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import java.util.Properties;

public final class App {
    private static final Logger LOG = LoggerFactory.getLogger(App.class);
    private static final ImmutableList<String> HEADERS = ImmutableList.of(
            "ID de cita", "Fecha", "Hora", "Paciente", "DNI", "Doctor",
            "Especialidad", "Modalidad", "Estado");
    private static final String SELECT = """
            SELECT c.id, c.fecha, c.hora,
                   CONCAT_WS(' ', p.nombres, p.apellidos), p.dni,
                   CONCAT_WS(' ', d.nombres, d.apellidos), d.especialidad,
                   c.modalidad, c.estado
            FROM citas c
            JOIN pacientes p ON p.id = c.paciente_id
            JOIN doctores d ON d.id = c.doctor_id
            WHERE 1 = 1
            """;

    public static void main(String[] args) {
        if (args.length != 5) {
            LOG.error("Uso: java -jar modulo-reportes.jar salida.xlsx inicio fin dni estado");
            System.exit(2);
        }
        try {
            int rows = export(Path.of(args[0]).toAbsolutePath(), args);
            LOG.info("Reporte Excel generado. Total de citas: {}", rows);
        } catch (Exception error) {
            LOG.error("No se pudo generar el Excel: {}", error.getMessage());
            System.exit(1);
        }
    }

    private static int export(Path output, String[] args) throws Exception {
        StringBuilder sql = new StringBuilder(SELECT);
        List<Object> parameters = new ArrayList<>();
        if (!args[1].isEmpty()) {
            sql.append(" AND c.fecha >= ?");
            parameters.add(LocalDate.parse(args[1]));
        }
        if (!args[2].isEmpty()) {
            sql.append(" AND c.fecha <= ?");
            parameters.add(LocalDate.parse(args[2]));
        }
        if (!args[3].isEmpty()) {
            sql.append(" AND p.dni = ?");
            parameters.add(args[3]);
        }
        if (!args[4].isEmpty()) {
            sql.append(" AND c.estado = ?");
            parameters.add(args[4]);
        }
        sql.append(" ORDER BY c.fecha DESC, c.hora DESC, c.id DESC");

        FileUtils.forceMkdir(output.getParent().toFile());
        Path temporary = Files.createTempFile(output.getParent(), "citas-", ".tmp");
        try {
            int count = writeWorkbook(temporary, sql.toString(), parameters);
            Files.move(temporary, output, StandardCopyOption.REPLACE_EXISTING);
            return count;
        } finally {
            Files.deleteIfExists(temporary);
        }
    }

    private static int writeWorkbook(Path temporary, String sql, List<Object> parameters)
            throws Exception {
        SXSSFWorkbook workbook = new SXSSFWorkbook();
        try (workbook;
             Connection connection = connect();
             PreparedStatement statement = connection.prepareStatement(sql)) {
            for (int index = 0; index < parameters.size(); index++) {
                statement.setObject(index + 1, parameters.get(index));
            }
            int count = 0;
            Sheet sheet = workbook.createSheet("Citas");
            sheet.createFreezePane(0, 1);
            Row header = sheet.createRow(0);
            for (int index = 0; index < HEADERS.size(); index++) {
                header.createCell(index).setCellValue(HEADERS.get(index));
                sheet.setColumnWidth(index, (index == 3 || index == 5 || index == 6 ? 32 : 15) * 256);
            }
            try (ResultSet results = statement.executeQuery()) {
                while (results.next()) {
                    Row row = sheet.createRow(++count);
                    row.createCell(0).setCellValue(results.getInt(1));
                    for (int index = 1; index < HEADERS.size(); index++) {
                        row.createCell(index).setCellValue(results.getString(index + 1));
                    }
                }
            }
            try (OutputStream stream = Files.newOutputStream(temporary)) {
                workbook.write(stream);
            }
            return count;
        } finally {
            workbook.dispose();
        }
    }

    private static Connection connect() throws Exception {

    String host = System.getenv()
            .getOrDefault("DB_HOST", "localhost");

    String port = System.getenv()
            .getOrDefault("DB_PORT", "3306");

    String database = System.getenv()
            .getOrDefault("DB_NAME", "sistema");

    String user = System.getenv("DB_USER");

    String password = System.getenv()
            .getOrDefault("DB_PASSWORD", "");

    if (user == null || user.isBlank()) {
        throw new IllegalArgumentException(
                "DB_USER es obligatorio."
        );
    }

    boolean local = host.equalsIgnoreCase("localhost")
            || host.equals("127.0.0.1");

    String ssl = System.getenv()
            .getOrDefault("DB_SSL", local ? "false" : "true");

    if (!ssl.equalsIgnoreCase("true")
            && !ssl.equalsIgnoreCase("false")) {
        throw new IllegalArgumentException(
                "DB_SSL debe ser true o false."
        );
    }

    boolean sslRequired = Boolean.parseBoolean(ssl);

    if (!local && !sslRequired) {
        throw new IllegalArgumentException(
                "TLS es obligatorio para conexiones remotas."
        );
    }

    Properties credentials = new Properties();

    credentials.setProperty("user", user);
    credentials.setProperty("password", password);

    credentials.setProperty(
            "sslMode",
            sslRequired ? "VERIFY_IDENTITY" : "PREFERRED"
    );

    credentials.setProperty("connectTimeout", "10000");
    credentials.setProperty("socketTimeout", "30000");

    String url = "jdbc:mysql://" + host
            + ":" + port + "/" + database;

    return DriverManager.getConnection(url, credentials);
    }
}
