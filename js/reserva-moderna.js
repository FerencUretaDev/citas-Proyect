/* Control exclusivo de la pantalla de nueva cita. */
document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    const form = $('formCita'), especialidad = $('especialidad_id'), doctor = $('doctor_id');
    const fecha = $('fecha'), hora = $('hora'), lista = $('listaHorarios'), mensaje = $('mensajeHorarios');
    const confirmar = $('btnGuardar'), info = $('infoDoctor');
    const opciones = Array.from(doctor.querySelectorAll('option[data-especialidad]'));
    const fechaTexto = value => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '';
        const [y,m,d] = value.split('-').map(Number);
        return new Intl.DateTimeFormat('es-PE',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(y,m-1,d));
    };
    let secuencia = 0;
    let controlador;
    let turnos = new Set();
    let enviando = false;
    function actualizarResumen() {
        const opcion = doctor.options[doctor.selectedIndex];
        const modalidad = form.querySelector('input[name="modalidad"]:checked');
        const partes = [];
        if (especialidad.value) partes.push(especialidad.value);
        if (doctor.value && opcion) partes.push(opcion.textContent.trim().replace(/\s+/g,' ').split(' - ')[0]);
        if (modalidad) partes.push(modalidad.value === 'virtual' ? 'Virtual' : 'Presencial');
        if (fecha.value) partes.push(fechaTexto(fecha.value));
        if (hora.value) partes.push(hora.value.slice(0,5));
        $('reservaResumen').textContent = doctor.value && fecha.value && hora.value ? partes.join(' · ') : (partes.length ? partes.join(' · ') + ' · Completa los datos pendientes.' : 'Completa los tres pasos para confirmar.');
        $('fechaElegida').textContent = fecha.value ? 'Fecha elegida: ' + fechaTexto(fecha.value) : 'Selecciona un día para ver los horarios.';
        confirmar.disabled = enviando || !especialidad.value || !doctor.value || !fecha.value || !turnos.has(hora.value);
    }
    function estado(texto, tipo = '') {
        mensaje.textContent = texto;
        mensaje.dataset.estado = tipo;
    }
    function mostrarDoctor() {
        const opcion = doctor.options[doctor.selectedIndex];
        const valido = Boolean(doctor.value && opcion && !opcion.disabled);
        info.classList.toggle('visible', valido);
        if (!valido) return;
        $('especialidadDoctor').textContent = opcion.dataset.especialidad;
        $('horarioDoctor').textContent = opcion.dataset.inicio.slice(0,5) + ' - ' + opcion.dataset.fin.slice(0,5);
        $('refrigerioDoctor').textContent = opcion.dataset.refrigerioInicio && opcion.dataset.refrigerioFin ? opcion.dataset.refrigerioInicio.slice(0,5) + ' - ' + opcion.dataset.refrigerioFin.slice(0,5) : 'Sin refrigerio registrado';
    }
    async function cargarHorarios() {
        const id = ++secuencia;
        controlador?.abort();
        controlador = undefined;
        turnos = new Set(); hora.value = ''; lista.replaceChildren(); lista.setAttribute('aria-busy','false');
        actualizarResumen();
        if (!doctor.value || !fecha.value || !fecha.checkValidity()) {
            estado(especialidad.value && doctor.disabled ? 'No hay doctores activos en esta especialidad. Selecciona otra.' : 'Selecciona un doctor y una fecha para consultar los turnos.');
            return;
        }
        controlador = new AbortController();
        lista.setAttribute('aria-busy','true');
        estado('Consultando horarios disponibles…');
        try {
            const consultaReglas = new URLSearchParams({reglas:'1', especialidad:especialidad.value, fecha:fecha.value});
            const respuestaReglas = await fetch('sacar_cita?' + consultaReglas, {signal:controlador.signal, cache:'no-store', headers:{Accept:'application/json'}});
            const reglas = await respuestaReglas.json();
            if (id !== secuencia) return;
            if (!respuestaReglas.ok || reglas.error || typeof reglas.misma_especialidad !== 'boolean' || !Array.isArray(reglas.horas_ocupadas)) {
                estado(typeof reglas.error === 'string' ? reglas.error : 'No se pudieron verificar tus citas. Intenta nuevamente.', 'error'); return;
            }
            if (reglas.misma_especialidad) {
                estado('Ya tienes una cita de esta especialidad en la fecha elegida. Selecciona otro día u otra especialidad.', 'error'); return;
            }
            const segundos = valor => {const [h,m,s] = valor.split(':').map(Number); return h*3600+m*60+s;};
            const ocupadas = reglas.horas_ocupadas.filter(v => typeof v === 'string' && /^\d{2}:\d{2}:\d{2}$/.test(v)).map(segundos);
            const coincide = valor => {const inicio = segundos(valor); return ocupadas.some(otro => inicio < otro+1800 && otro < inicio+1800);};
            const params = new URLSearchParams({doctor_id:doctor.value,fecha:fecha.value});
            const respuesta = await fetch('horarios_disponibles?' + params, {signal:controlador.signal, cache:'no-store', headers:{Accept:'application/json'}});
            const datos = await respuesta.json();
            if (id !== secuencia) return;
            if (datos.error || !respuesta.ok) {estado(typeof datos.error === 'string' ? datos.error : 'No se pudieron consultar los horarios. Intenta nuevamente.', 'error'); return;}
            const horarios = Array.isArray(datos.horarios) ? datos.horarios.filter(v => typeof v === 'string' && /^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(v)) : [];
            turnos = new Set(horarios.filter(valor => !coincide(valor)));
            if (!turnos.size) {estado(ocupadas.length ? 'No hay turnos compatibles con tus citas de ese día. Selecciona otra fecha o profesional.' : 'No hay horarios disponibles para esta fecha. Elige otro día.'); return;}
            estado(`${turnos.size} ${turnos.size === 1 ? 'turno disponible' : 'turnos disponibles'}. Selecciona una hora.` + (ocupadas.length ? ' Se excluyen los cruces con tus otras citas.' : ''));
            for (const valor of turnos) {
                const boton = document.createElement('button');
                boton.type='button'; boton.className='horario'; boton.textContent=valor.slice(0,5);
                boton.setAttribute('aria-pressed','false'); boton.setAttribute('aria-label','Seleccionar horario ' + boton.textContent);
                boton.addEventListener('click', () => {
                    lista.querySelectorAll('button').forEach(b => {b.classList.remove('seleccionado'); b.setAttribute('aria-pressed','false');});
                    boton.classList.add('seleccionado'); boton.setAttribute('aria-pressed','true'); hora.value=valor; actualizarResumen();
                });
                lista.append(boton);
            }
        } catch (error) {
            if (id === secuencia && error.name !== 'AbortError') estado('No se pudieron consultar los horarios. Cambia la fecha o vuelve a intentarlo.', 'error');
        } finally {
            if (id === secuencia) lista.setAttribute('aria-busy','false');
        }
    }
    function filtrarDoctores(limpiar) {
        const hay = opciones.some(o => o.dataset.especialidad === especialidad.value);
        doctor.disabled = !hay;
        doctor.options[0].textContent = !especialidad.value ? 'Selecciona primero una especialidad' : hay ? 'Selecciona un doctor' : 'Sin doctores activos';
        opciones.forEach(o => {const coincide = especialidad.value !== '' && o.dataset.especialidad === especialidad.value; o.hidden = o.disabled = !coincide; if (!coincide && o.selected) doctor.value='';});
        if (limpiar) doctor.value='';
        mostrarDoctor(); cargarHorarios();
    }
    especialidad.addEventListener('change', () => filtrarDoctores(true));
    doctor.addEventListener('change', () => {mostrarDoctor(); cargarHorarios();});
    fecha.addEventListener('change', cargarHorarios);
    form.querySelectorAll('input[name="modalidad"]').forEach(r => r.addEventListener('change', actualizarResumen));
    // El campo nativo permanece disponible si el CDN no carga.
    if (typeof window.flatpickr === 'function') {
        try {
            const calendario = window.flatpickr($('calendarioVisor'), {
                inline:true, disableMobile:true, locale:window.flatpickr.l10ns.es || 'default',
                dateFormat:'Y-m-d', minDate:fecha.min || 'today',
                onChange:(_dates, value) => {fecha.value=value; cargarHorarios();}
            });
            if (calendario && calendario.calendarContainer) {
                $('reservaFechaAlternativa').hidden=true;
                fecha.type='hidden'; // sigue enviándose al servidor, sin validación nativa de un control invisible
            }
        } catch (error) { console.warn('Calendario alternativo disponible.', error); }
    }
    if (!$('reservaFechaAlternativa').hidden) {
        $('calendarioVisor').hidden=true;
        document.querySelector('.reserva-leyenda').hidden=true;
    }
    form.addEventListener('submit', e => {
        if (confirmar.disabled || enviando || !turnos.has(hora.value)) {e.preventDefault(); return;}
        enviando=true; confirmar.disabled=true; confirmar.textContent='Registrando cita…';
    });
    window.addEventListener('pageshow', e => {if(e.persisted){enviando=false; confirmar.innerHTML='Confirmar cita <span aria-hidden="true">→</span>'; cargarHorarios();}});
    filtrarDoctores(false);
});
