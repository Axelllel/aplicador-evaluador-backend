<a class="sh-exam-card sh-done" href="{{ route('student.grades', ['intento' => $result['intento_id'], 'grupo' => $groupFilter ?? null]) }}">
    <div class="sh-exam-top">
        <span class="sh-subject-icon"><x-icon name="document-text" /></span>
        <span class="sh-badge sh-badge-done">✓ Completado</span>
    </div>
    <div class="sh-exam-body">
        <p class="sh-subject-name">{{ $result['materia'] }} · Grupo #{{ $result['grupo_id'] }}</p>
        <h3>{{ $result['nombre'] }}</h3>
        @if ($result['fecha_entrega'])
            @php
                $delivered = \Carbon\CarbonImmutable::parse($result['fecha_entrega']);
                $minutes = (int) \Carbon\CarbonImmutable::parse($result['fecha_inicio'])->diffInMinutes($delivered, true);
            @endphp
            <div class="sh-exam-meta">
                <span><time datetime="{{ $delivered->toIso8601String() }}">{{ $delivered->locale('es_MX')->isoFormat('D MMM YYYY') }}</time></span>
                <span>{{ $minutes ?: 'Menos de 1' }} min empleados</span>
            </div>
        @endif
        <div class="sh-exam-bottom">
            @if ($result['calificacion'] !== null)
                <span class="sh-grade"><strong>{{ $result['calificacion'] }}</strong><span>/ {{ $result['escala'] === '0-10' ? '10' : '100' }}</span></span>
                @if ($result['estado'] === 'aproximado')
                    <span class="sg-provisional-label">Provisional</span>
                @endif
            @else
                <span class="sh-question-count">Resultado pendiente</span>
            @endif
        </div>
    </div>
</a>
