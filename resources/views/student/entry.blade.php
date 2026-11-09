{{--
Archivo: entry.blade.php
Módulo: resources/views/student
Descripción: Ingreso de alumnos mediante folio/QR y nombre; usa la API existente.
Nota: No crea cuentas ni autoriza intentos de examen.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1F2937"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingreso del estudiante · BigBitByte</title>
    @vite(['resources/css/student-entry.css', 'resources/js/student-entry.js'])
</head>
<body>
<div class="portal" id="student-entry" data-join-url="{{ route('student.join') }}" data-validate-url="{{ route('student.validate-folio') }}">
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <div class="background-grid" aria-hidden="true"></div>

    <header class="site-header">
        <div class="brand-row">
            <img class="brand-logo" src="{{ asset('images/bigbitbyte-logo.png') }}" alt="Logotipo de BigBitByte">
            <div class="wordmark">BigBit<span>Byte</span><span class="brand-period">.</span></div>
        </div>
        <div class="header-actions">
            <span class="portal-label"><span class="status-dot"></span>Portal del estudiante</span>
            <span class="header-divider" aria-hidden="true"></span>
            <button type="button" class="icon-button theme-button" id="toggle-theme" title="Cambiar tema" aria-label="Activar modo oscuro">☾</button>
        </div>
    </header>

    <main>
        <div class="entry-layout">
            <div class="eyebrow"><span></span>TU SIGUIENTE GRAN IDEA EMPIEZA AQUÍ</div>
            <section class="entry-card" aria-label="Ingreso al grupo">
                <div class="card-top-line"></div>
                <div class="steps" aria-label="Proceso de ingreso en dos pasos">
                    <div class="step active" id="step-indicator-1"><span class="step-number">1</span><span>Tu grupo</span></div>
                    <div class="step-line" id="step-indicator-line"></div>
                    <div class="step" id="step-indicator-2"><span class="step-number">2</span><span>Tu nombre</span></div>
                </div>
                <div class="card-content" id="entry-step-one">
                    <div class="welcome-icon" aria-hidden="true">▤</div>
                    <div class="heading-block">
                        <h1>Bienvenido a <span>BigBitByte</span></h1>
                        <p>Ingresa al grupo de tu docente</p>
                    </div>
                    <form id="folio-form" novalidate>
                        <label for="folio">Folio del grupo</label>
                        <div class="input-wrap" id="folio-wrap">
                            <span aria-hidden="true">#</span>
                            <input id="folio" name="folio" placeholder="Ej. BBB2026" autocomplete="off" maxlength="20" spellcheck="false" required>
                        </div>
                        <p class="field-hint">Tu docente te compartirá el folio de tu grupo.</p>
                        <p role="alert" class="entry-message" id="folio-feedback" hidden></p>
                        <button class="primary-button" type="submit">Continuar &nbsp; →</button>
                    </form>
                    <div class="separator"><span></span>o<span></span></div>
                    <button type="button" class="secondary-button" id="scan-qr">▦ &nbsp; Escanear código QR</button>
                    <p class="qr-caption">Otra forma de llegar al mismo lugar.</p>
                </div>
                <div class="card-content" id="entry-step-two" hidden>
                    <div class="welcome-icon" aria-hidden="true">♙</div>
                    <div class="heading-block"><h1>Identifícate</h1><p>Estamos a un paso de empezar.</p></div>
                    <div class="group-info">
                        <div class="group-icon" aria-hidden="true">▤</div>
                        <div>
                            <span class="group-kicker">FOLIO DEL GRUPO</span>
                            <strong id="folio-preview"></strong>
                            <span>Grupo activo verificado en Supabase.</span>
                        </div>
                        <span class="verified" aria-hidden="true">✓</span>
                    </div>
                    <form id="student-form" novalidate>
                        <label for="student-name">Nombre completo <span class="required">*</span></label>
                        <div class="input-wrap name-input" id="name-wrap"><input id="student-name" name="nombre" placeholder="Escribe tu nombre y apellidos" autocomplete="name" maxlength="150" value="{{ $enrolledStudent['nombre'] ?? '' }}" @readonly($enrolledStudent) required></div>
                        <p class="field-hint">{{ $enrolledStudent ? 'Usaremos el nombre de tu sesión para agregarte al grupo.' : 'Así te reconocerá tu docente en el grupo.' }}</p>
                        <p role="alert" class="entry-message" id="name-feedback" hidden></p>
                        <button class="primary-button" type="submit" id="join-button">Unirme al grupo &nbsp; →</button>
                    </form>
                    <button type="button" class="back-button" id="back-button">← &nbsp; Volver</button>
                    <p class="transition-status">Sin cuentas. Sin contraseñas.</p>
                </div>
                <div class="card-content" id="entry-step-success" hidden>
                    <div class="welcome-icon success-icon" aria-hidden="true">✓</div>
                    <div class="heading-block success-heading">
                        <span class="success-label">INSCRIPCIÓN COMPLETADA</span>
                        <h1 id="success-title">¡Todo listo!</h1>
                        <p>Tu inscripción al grupo fue registrada.</p>
                    </div>
                    <div class="success-box"><span>Materia registrada</span><strong id="success-subject"></strong></div>
                    <p class="prototype-note">Ya puedes consultar los exámenes de tu grupo e iniciar los que estén disponibles.</p>
                    <a class="primary-button" id="success-home" style="display:block;text-align:center;text-decoration:none;margin-top:14px" href="{{ route('student.home') }}">Ir al portal del estudiante →</a>
                    <button class="secondary-button" type="button" id="reset-button">Volver al ingreso</button>
                </div>
                <div class="card-footer"><span aria-hidden="true">♢</span><span>Un espacio seguro para aprender y crear.</span></div>
            </section>
            <div class="below-card"><span class="mini-line"></span><p>Pequeños pasos. <strong>Grandes posibilidades.</strong></p><span class="mini-line"></span></div>
        </div>
    </main>
    <footer class="site-footer"><p>© {{ date('Y') }} BigBitByte <span>Aprender. Crear. Crecer.</span></p><button class="help-button" type="button" id="help-button">¿Necesitas ayuda?</button></footer>
    <dialog class="modal" id="qr-dialog" aria-label="Escanear código QR">
        <button type="button" class="icon-button close-button" id="close-qr" aria-label="Cerrar">✕</button>
        <div class="welcome-icon">▦</div>
        <h2>Escanear código QR</h2>
        <p>Apunta la cámara al código compartido por tu docente.</p>
        <video id="qr-video" autoplay playsinline muted hidden></video>
        <p class="scanner-message" id="scanner-message" role="status"></p>
        <button class="primary-button" type="button" id="qr-cancel">Volver al ingreso</button>
    </dialog>
    <dialog class="modal" id="help-dialog" aria-label="Ayuda para ingresar">
        <button type="button" class="icon-button close-button" id="close-help" aria-label="Cerrar">✕</button>
        <div class="welcome-icon">?</div>
        <h2>Vamos paso a paso</h2>
        <p>1. Solicita el folio o QR a tu docente.<br>2. Escribe tu nombre completo.<br>3. Únete al grupo.</p>
        <div class="help-note">No necesitas correo, contraseña ni crear una cuenta.</div>
        <button class="primary-button" type="button" id="help-ok">Entendido</button>
    </dialog>
</div>
</body>
</html>
