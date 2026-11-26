// Validación visual; PHP conserva la validación obligatoria en el servidor.
(() => {
    "use strict";
    for (const form of document.querySelectorAll(".doctor-form, .paciente-form")) {
        const password = form.querySelector('[name="password"]');
        const confirmar = form.querySelector('[name="confirmar_password"]');
        const mostrar = form.querySelector('[data-mostrar-password]');
        const bloque = form.querySelector('[data-password-admin]');
        if (!password || !confirmar || !mostrar || !bloque) continue;
        const estado = bloque.querySelector('[data-password-estado]');
        const obligatorio = password.required;
        const patron = /^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{Cc}\p{Zl}\p{Zp}]{8,}$/u;
        const validar = () => {
            const valor = password.value;
            const repetida = confirmar.value;
            const vacios = valor === "" && repetida === "";
            const reglas = {
                longitud: Array.from(valor).length >= 8,
                mayuscula: /\p{Lu}/u.test(valor),
                minuscula: /\p{Ll}/u.test(valor),
                numero: /[0-9]/.test(valor),
                simbolo: /[\p{P}\p{S}]/u.test(valor),
                coincidencia: valor !== "" && valor === repetida
            };
            for (const elemento of bloque.querySelectorAll('[data-regla]')) {
                const cumple = reglas[elemento.dataset.regla];
                elemento.classList.toggle('cumple', !vacios && cumple);
                elemento.classList.toggle('no-cumple', !vacios && !cumple);
            }
            password.setCustomValidity("");
            confirmar.setCustomValidity("");
            confirmar.required = obligatorio || valor !== "";
            let mensaje = "";
            if (!vacios || obligatorio) {
                if (!patron.test(valor)) {
                    password.setCustomValidity("Mínimo 8 caracteres: mayúscula, minúscula, número y símbolo; sin caracteres de control.");
                } else if (new TextEncoder().encode(valor).length > 72) {
                    password.setCustomValidity("La contraseña no puede superar 72 bytes.");
                }
                if (valor !== repetida) confirmar.setCustomValidity("Las contraseñas no coinciden.");
                mensaje = password.validationMessage || confirmar.validationMessage || "La contraseña cumple todos los requisitos.";
            }
            const valido = !password.validationMessage && !confirmar.validationMessage;
            estado.textContent = vacios ? "" : mensaje;
            estado.classList.toggle('cumple', !vacios && valido);
            estado.classList.toggle('no-cumple', !vacios && !valido);
        };
        password.addEventListener("input", validar);
        confirmar.addEventListener("input", validar);
        form.addEventListener("submit", evento => {
            validar();
            if (!form.reportValidity()) evento.preventDefault();
        });
        mostrar.hidden = false;
        mostrar.addEventListener("click", () => {
            const visible = password.type === "password";
            password.type = confirmar.type = visible ? "text" : "password";
            mostrar.querySelector('[data-texto-password]').textContent = visible ? "Ocultar contraseñas" : "Mostrar contraseñas";
            mostrar.setAttribute("aria-pressed", String(visible));
        });
        validar();
    }
})();
