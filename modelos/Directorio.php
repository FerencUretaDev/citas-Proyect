<?php
final class Directorio
{
    private string $tabla;
    private string $claveCita;

    public function __construct(private mysqli $db, string $tipo)
    {
        [$this->tabla, $this->claveCita] = match ($tipo) {
            'doctor' => ['doctores', 'doctor_id'],
            'paciente' => ['pacientes', 'paciente_id'],
        };
    }

    public function ficha(int $id, bool $bloquear = false): ?array
    {
        $sql = "SELECT nombres, apellidos, activo, motivo_baja, fecha_baja FROM {$this->tabla} WHERE id = ?";
        $stmt = $this->db->prepare($sql . ($bloquear ? ' FOR UPDATE' : ''));
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function citasFuturas(int $id): int
    {
        $ahora = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM citas WHERE {$this->claveCita} = ?
            AND TIMESTAMP(fecha, hora) >= ? AND estado IN ('pendiente','confirmada')");
        $stmt->bind_param('is', $id, $ahora);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    public function cambiarEstado(int $id, bool $reactivar, string $motivo): void
    {
        $this->db->begin_transaction();
        try {
            $ficha = $this->ficha($id, true);
            if (!$ficha || (bool)$ficha['activo'] === $reactivar) {
                throw new DomainException('La ficha cambió. Vuelve al directorio para revisar su estado.');
            }
            if (!$reactivar && $this->citasFuturas($id) > 0) {
                throw new DomainException('Hay citas futuras pendientes o confirmadas. Resuélvelas antes de continuar.');
            }
            if ($reactivar) {
                $stmt = $this->db->prepare("UPDATE {$this->tabla} SET activo=1, motivo_baja=NULL, fecha_baja=NULL WHERE id=?");
                $stmt->bind_param('i', $id);
            } else {
                $stmt = $this->db->prepare("UPDATE {$this->tabla} SET activo=0, motivo_baja=?, fecha_baja=NOW() WHERE id=?");
                $stmt->bind_param('si', $motivo, $id);
            }
            $stmt->execute();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
