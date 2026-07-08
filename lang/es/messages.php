<?php

return [
    'description' => 'Listas de solo lectura de los commits, merge requests e incidencias de un proyecto GitLab.',
    'mode' => [
        'commits' => 'Commits recientes',
        'merge_requests' => 'Merge requests abiertas',
        'issues' => 'Incidencias abiertas',
    ],
    'field' => [
        'project' => 'Proyecto',
        'project_help' => 'Formato namespace/proyecto, p. ej. gitlab-org/gitlab.',
        'project_placeholder' => 'namespace/proyecto',
    ],
    'oauth' => [
        'instance_url' => 'URL de la instancia GitLab',
        'instance_url_help' => 'La raíz de tu GitLab, p. ej. https://gitlab.ejemplo.com. Déjalo en blanco para gitlab.com.',
        'client_id' => 'OAuth GitLab · Application ID',
        'client_id_help' => 'Desde GitLab → Preferencias → Aplicaciones (scope: read_api).',
        'client_secret' => 'OAuth GitLab · Secret',
        'client_secret_help' => 'Déjalo en blanco para conservar el valor guardado.',
    ],
];
