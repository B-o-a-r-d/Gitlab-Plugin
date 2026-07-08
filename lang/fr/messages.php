<?php

return [
    'description' => 'Listes en lecture seule des commits, merge requests et tickets d\'un projet GitLab.',
    'mode' => [
        'commits' => 'Derniers commits',
        'merge_requests' => 'Merge requests ouvertes',
        'issues' => 'Tickets ouverts',
    ],
    'field' => [
        'project' => 'Projet',
        'project_help' => 'Format namespace/projet, ex. gitlab-org/gitlab.',
        'project_placeholder' => 'namespace/projet',
    ],
    'oauth' => [
        'instance_url' => 'URL de l\'instance GitLab',
        'instance_url_help' => 'La racine de ton GitLab, ex. https://gitlab.exemple.com. Laisse vide pour gitlab.com.',
        'client_id' => 'OAuth GitLab · Application ID',
        'client_id_help' => 'Depuis GitLab → Préférences → Applications (scope : read_api).',
        'client_secret' => 'OAuth GitLab · Secret',
        'client_secret_help' => 'Laissez vide pour conserver la valeur enregistrée.',
    ],
];
