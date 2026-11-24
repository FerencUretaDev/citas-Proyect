const consultarDni = document.querySelector('#consultar-dni');
if (consultarDni) {
    const form = consultarDni.form;
    const dni = form.elements.dni;
    const aviso = document.querySelector('#resultado-dni');
    const nombres = ['nombres', 'primer_apellido', 'segundo_apellido'];
    dni.addEventListener('input', () => { aviso.textContent = ''; });
    consultarDni.addEventListener('click', async () => {
        const numero = dni.value;
        if (!/^[0-9]{8}$/.test(numero)) {
            aviso.textContent = 'Ingresa los 8 dígitos del DNI.';
            dni.focus();
            return;
        }
        const anteriores = nombres.map(campo => form.elements[campo].value);
        consultarDni.disabled = true;
        aviso.textContent = 'Consultando DNI…';
        try {
            const respuesta = await fetch('consultar_dni', {
                method: 'POST',
                body: new URLSearchParams({dni: numero, csrf_token: form.elements.csrf_token.value})
            });
            const datos = await respuesta.json();
            if (dni.value !== numero) return;
            if (!respuesta.ok) {
                aviso.textContent = datos.error || 'Consulta no disponible.';
                return;
            }
            if (nombres.some((campo, i) => form.elements[campo].value !== anteriores[i])) {
                aviso.textContent = 'Los nombres cambiaron durante la consulta. Pulsa Consultar DNI para volver a cargar los datos.';
                return;
            }
            nombres.forEach(campo => { form.elements[campo].value = datos[campo]; });
            aviso.textContent = 'Datos recibidos. Revísalos antes de registrar al paciente.';
        } catch {
            if (dni.value === numero) aviso.textContent = 'No se pudo consultar. Puedes ingresar los nombres manualmente.';
        } finally {
            consultarDni.disabled = false;
        }
    });
}
