<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../servicios/UsuarioService.php';
require_once __DIR__ . '/../servicios/ReservaService.php';
require_once __DIR__ . '/../servicios/CitaService.php';
$casos = 0;
function comprobar(bool $resultado, string $nombre): void {
    global $casos;
    if (!$resultado) { fwrite(STDERR, "FALLO: $nombre\n"); exit(1); }
    $casos++;
    echo "OK: $nombre\n";
}
comprobar(baseUsuario('Ana María','López','Torres',0)==='pac.alopezt','Usuario paciente');
comprobar(baseUsuario('Óscar','Muñoz','Ávila',1)==='doc.omunoza','Usuario doctor y acentos');
comprobar(baseUsuario('Ana','López','',0)==='pac.alopez','Segundo apellido opcional');
comprobar(baseUsuario('123','López','',0)==='','Nombre inválido');
comprobar(strlen(baseUsuario('Ana',str_repeat('a',80),'T',0))===50,'Límite de usuario');
$mapa = ['medicina general'=>'Medicina General','medecina general'=>'Medicina General','cardiologia'=>'Cardiología'];
$citas = [['hora'=>'13:00:00','especialidad'=>'Medecina General']];
$r = resumenRestriccionesReserva($citas,'Medicina General',$mapa);
comprobar($r['misma_especialidad'] && conflictoReserva($r,'17:00:00')!==null,'Una cita por especialidad y día, alias incluido');
$r = resumenRestriccionesReserva($citas,'Cardiología',$mapa);
comprobar(conflictoReserva($r,'13:00:00')!==null,'Mismo horario');
comprobar(conflictoReserva($r,'13:15:00')!==null,'Cruce parcial posterior');
comprobar(conflictoReserva($r,'12:45:00')!==null,'Cruce parcial anterior');
comprobar(conflictoReserva($r,'13:30:00')===null,'Turno contiguo');
comprobar(conflictoReserva($r,'12:30:00')===null,'Turno anterior contiguo');
comprobar(conflictoReserva(resumenRestriccionesReserva([],'Medicina General',$mapa),'13:00:00')===null,'Sin citas previas');
comprobar(TRANSICIONES_ESTADO_CITA['atendida']===[] && TRANSICIONES_ESTADO_CITA['cancelada']===[],'Estados terminales');
comprobar(in_array('confirmada',TRANSICIONES_ESTADO_CITA['pendiente'],true),'Transición pendiente a confirmada');
echo "Total: $casos pruebas superadas.\n";
