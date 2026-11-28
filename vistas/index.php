<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<title>Iniciar Sesión - Sistema de Citas Médicas</title>




    <link rel="stylesheet" href="css/style.css?v=mejora12-login-v2">
    <link rel="stylesheet" href="css/registro-paciente.css?v=12-1">
</head>


<body class="page-login">


<!-- DECORACIÓN -->

<div class="decoracion decoracion-1"></div>

<div class="decoracion decoracion-2"></div>

<div class="cruz-fondo">+</div>


<!-- =====================================================
     LOGIN
===================================================== -->

<div class="login-wrapper">


    <!-- =================================================
         IZQUIERDA
    ================================================== -->

    <section class="panel-izquierdo">


        <div class="logo">

            <div class="logo-icon">
                +
            </div>

            <div>

                <h1>
                    Sistema de<br>
                    Citas Médicas
                </h1>

                <p>
                    Tu salud, nuestra prioridad
                </p>

            </div>

        </div>


        <!-- ILUSTRACIÓN -->

        <div class="doctor-ilustracion">

            <div class="doctor-circle">

                <div class="doctor">

                    <div class="cabello"></div>

                    <div class="cabeza"></div>

                    <div class="cuerpo">

                        <div class="camisa"></div>

                        <div class="corazon">
                            ♥
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- BENEFICIOS -->

        <div class="beneficios">

            <div class="beneficio">

                <div class="beneficio-icono">
                    📅
                </div>

                <strong>
                    Agenda tu cita
                </strong>

                <small>
                    Fácil y rápido
                </small>

            </div>


            <div class="beneficio">

                <div class="beneficio-icono">
                    🛡️
                </div>

                <strong>
                    Atención segura
                </strong>

                <small>
                    Información protegida
                </small>

            </div>


            <div class="beneficio">

                <div class="beneficio-icono">
                    ♥
                </div>

                <strong>
                    Tu bienestar
                </strong>

                <small>
                    Siempre primero
                </small>

            </div>

        </div>


    </section>


    <!-- =================================================
         DERECHA
    ================================================== -->

    <section class="panel-derecho">


        <div class="login-titulo">

            <h2>
                Iniciar Sesión
            </h2>

            <p>
                Accede a tu cuenta para gestionar
                tus citas médicas.
            </p>

        </div>


        <?php if ($mensaje != ""): ?>

            <div class="mensaje">

                ⚠️

                <?php
                echo htmlspecialchars($mensaje);
                ?>

            </div>

        <?php endif; ?>


        <form method="POST"
              action="">


            <!-- USUARIO -->

            <div class="campo">

                <label for="usuario">
                    Usuario
                </label>

                <div class="input-wrapper">

                    <span class="input-icono">
                        👤
                    </span>

                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        placeholder="Ingresa tu usuario"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- CONTRASEÑA -->

            <div class="campo">

                <label for="password">
                    Contraseña
                </label>

                <div class="input-wrapper">

                    <span class="input-icono">
                        🔒
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="ver-password"
                        aria-label="Mostrar contraseña"
                        id="botonPassword"
                    >
                        👁
                    </button>

                </div>

            </div>


            <!-- RECUPERAR -->

            <div class="opciones">

                <a href="#">
                    ¿Olvidaste tu contraseña?
                </a>

            </div>


            <!-- INGRESAR -->

            <button
                type="submit"
                class="btn-ingresar"
            >

                ➜ &nbsp; Ingresar

            </button>


        </form>

        <div class="registro-login">
            ¿Todavía no tienes una cuenta?
            <a href="registro_paciente.php">Registrarme como paciente</a>
        </div>


        <div class="divisor">
            o
        </div>


        <div class="seguridad">

            🔐 Tus datos están protegidos.

            <br>

            Sistema de Citas Médicas

        </div>


    </section>


</div>


<!-- FOOTER -->

<div class="footer">

    Sistema de Citas Médicas
    &nbsp;©&nbsp;
    2026
    -
    Todos los derechos reservados

</div>


<script src="js/javascript.js" defer></script>


</body>

</html>
