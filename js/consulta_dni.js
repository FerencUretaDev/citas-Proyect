const consultarDni = document.querySelector('#consultar-dni');
if (consultarDni) {
    const form = consultarDni.form;
    const dni = form.elements.dni;
    const aviso = document.querySelector('#resultado-dni');
    // Altas: apellidos separados. Edición de doctor: apellidos en una sola columna.
    const separados = Boolean(form.elements.primer_apellido && form.elements.segundo_apellido);
    const campos = separados ? ['nombres', 'primer_apellido', 'segundo_apellido'] : ['nombres', 'apellidos'];
    let versionDni = 0;
    dni.addEventListener('input', () => {
        versionDni++;
        aviso.textContent = 'DNI modificado. Consulta o revisa los nombres antes de guardar.';
    });
    consultarDni.addEventListener('click', async () => {
        const numero = dni.value;
        const version = versionDni;
        if (!/^[0-9]{8}$/.test(numero)) {
            aviso.textContent = 'Ingresa los 8 dígitos del DNI.';
            dni.focus();
            return;
        }
        const anteriores = campos.map(campo => form.elements[campo].value);
        consultarDni.disabled = true;
        aviso.textContent = 'Consultando DNI…';
        try {
            const respuesta = await fetch('consultar_dni', {
                method: 'POST',
                body: new URLSearchParams({dni: numero, csrf_token: form.elements.csrf_token.value})
            });
            const datos = await respuesta.json();
            if (dni.value !== numero || versionDni !== version) return;
            if (!respuesta.ok) {
                aviso.textContent = datos.error || 'Consulta no disponible.';
                return;
            }
            if (!['nombres','primer_apellido','segundo_apellido'].every(c => typeof datos[c] === 'string') || !datos.nombres.trim() || !datos.primer_apellido.trim()) {
                aviso.textContent = 'No se recibieron datos válidos. Revisa el DNI.';
                return;
            }
            if (campos.some((campo, i) => form.elements[campo].value !== anteriores[i])) {
                aviso.textContent = 'Los nombres cambiaron durante la consulta. Pulsa Consultar DNI para volver a cargar los datos.';
                return;
            }
            const valores = {...datos, apellidos: [datos.primer_apellido, datos.segundo_apellido].filter(Boolean).join(' ')};
            campos.forEach(campo => { form.elements[campo].value = valores[campo]; });
            aviso.textContent = 'Datos recibidos. Revísalos antes de guardar la ficha.';
        } catch {
            if (dni.value === numero && versionDni === version) aviso.textContent = 'No se pudo consultar. Puedes ingresar los nombres manualmente.';
        } finally {
            consultarDni.disabled = false;
        }
    });
}
