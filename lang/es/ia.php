<?php

declare(strict_types=1);

return [
    'proceso' => ['pendiente' => 'La propuesta automática está pendiente. Puedes continuar con la revisión manual.', 'procesando' => 'La propuesta automática se está preparando.', 'fallido' => 'No se completó la propuesta automática. Puedes solicitarla nuevamente o calificar manualmente.'],
    'titulo' => 'Generar preguntas con IA',
    'intro' => 'Describe el tema y revisa las propuestas antes de agregarlas a tu banco.',
    'tema' => 'Tema o aprendizaje esperado',
    'tipo' => 'Tipo de pregunta',
    'nivel' => 'Dificultad',
    'niveles' => ['basico' => 'Básica', 'intermedio' => 'Intermedia', 'avanzado' => 'Avanzada'],
    'cantidad' => 'Cantidad de preguntas',
    'contexto' => 'Material de referencia (opcional)',
    'contexto_ayuda' => 'Puedes incluir conceptos o criterios del curso. Evita nombres, correos y datos personales.',
    'generar' => 'Generar propuestas',
    'generando' => 'Generando preguntas…',
    'borradores' => 'Propuestas generadas',
    'revisar' => 'Revisar y editar',
    'sin_guardar' => 'Estas propuestas aún no están en el banco. Abre una, revísala y guarda la pregunta que quieras usar.',
    'respuesta_modelo' => 'Respuesta de referencia',
    'error' => 'La IA no pudo completar la consulta. Puedes continuar trabajando manualmente.',
    'propuesta' => 'Propuesta de calificación con IA',
    'proponer' => 'Consultar IA para esta respuesta',
    'evaluando' => 'Preparando propuesta…',
    'puntaje' => 'Puntaje sugerido: :nota de 100',
    'sin_puntaje' => 'Se necesita una revisión manual para proponer un puntaje.',
    'revision' => 'La IA detectó aspectos que requieren especial revisión.',
    'aviso_docente' => 'Esta es una sugerencia. Revisa la explicación y el puntaje; Guardar registra tu calificación final.',
    'modelo' => 'Generado con :modelo · Groq',
    'motivo' => 'Revisión docente de propuesta IA: :explicacion',
    'calificacion_ayuda' => 'Consulta opcional para respuestas abiertas y programación. La opción múltiple se resuelve sin IA.',
];
