<dialog class="sh-dialog ei-dialog" id="bbb-exam-details" aria-labelledby="bbb-exam-details-title">
    <form method="dialog">
        <button class="sh-icon-button sh-dialog-close" type="submit" aria-label="Cerrar detalles"><x-icon name="x-mark" /></button>
    </form>
    <div class="ei-heading">
        <div class="ei-icon"><x-icon name="document-text" /></div>
        <div>
            <p class="ei-eyebrow">ANTES DE COMENZAR</p>
            <p class="ei-subject">{{ $student['materia'] }}</p>
        </div>
    </div>
    <h2 id="bbb-exam-details-title"></h2>
    <div class="ei-exam-context">
        <span><x-icon name="user-group" /> Grupo #{{ $student['grupo_id'] }}</span>
        <span class="ei-state"><span aria-hidden="true"></span><strong id="bbb-exam-details-state"></strong></span>
    </div>

    <dl class="ei-facts" id="bbb-exam-details-facts" hidden>
        <div class="ei-date">
            <dt>Horario de aplicación (hora local)</dt>
            <dd id="bbb-exam-details-schedule"></dd>
        </div>
        <div>
            <dt>Duración estimada</dt>
            <dd id="bbb-exam-details-duration"></dd>
        </div>
        <div>
            <dt>Preguntas</dt>
            <dd id="bbb-exam-details-questions"></dd>
        </div>
    </dl>

    <div class="ei-instructions">
        <h3><x-icon name="document-text" /> Instrucciones generales</h3>
        <ol>
            <li>Lee cada pregunta y sigue las indicaciones antes de responder.</li>
            <li>Administra tu tiempo y revisa tus respuestas antes de entregar.</li>
            <li>Al terminar, selecciona «Entregar examen» y confirma la entrega.</li>
        </ol>
    </div>
    <div class="ei-time-warning">
        <x-icon name="information-circle" />
        <div>
            <strong>El tiempo cuenta desde que inicias</strong>
            <p>Ten a la mano el pase individual de tu docente y revisa la hora de cierre.</p>
        </div>
    </div>
    <p class="ei-availability" id="bbb-exam-details-upcoming" hidden>Podrás ingresar el pase cuando abra el examen.</p>
    @if ($attempt)
        <p class="ei-availability" id="bbb-exam-details-existing" hidden>Ya tienes un examen iniciado o entregado en esta sesión.</p>
    @endif
    <form class="ei-actions" method="dialog">
        <button class="ei-cancel" type="submit">Cerrar</button>
        @unless ($attempt)
            <button class="ei-start" type="button" data-exam-pass hidden>Ingresar pase →</button>
        @endunless
    </form>
</dialog>
