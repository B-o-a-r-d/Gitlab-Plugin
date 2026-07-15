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
    'settings' => [
        'default_instance_url' => 'Instancia GitLab por defecto',
        'default_instance_url_help' => 'Se usa cuando un tablero no define su propia URL de instancia. Déjalo en blanco para gitlab.com.',
        'allowed_hosts' => 'Hosts internos permitidos',
        'allowed_hosts_help' => 'Hosts en una red privada, separados por comas, que los tableros pueden usar (p. ej. un GitLab autoalojado). Los hosts públicos siempre se permiten; los internos deben listarse aquí.',
    ],
    'automation' => [
        'create_issue' => 'Crear una issue de GitLab',
        'project' => 'Proyecto (grupo/proyecto o ID)',
        'title' => 'Título ({card}, {board}, {list} se reemplazan — vacío = título de la tarjeta)',
        'body' => 'Descripción (mismos marcadores, opcional)',
        'labels' => 'Etiquetas (separadas por comas, opcional)',
        'issue_created' => 'Incidencia de GitLab creada',
        'open_issue' => 'Abrir la incidencia',
    ],
];
