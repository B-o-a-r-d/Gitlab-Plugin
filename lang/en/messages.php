<?php

return [
    'description' => 'Read-only lists of a GitLab project\'s commits, merge requests and issues.',
    'mode' => [
        'commits' => 'Recent commits',
        'merge_requests' => 'Open merge requests',
        'issues' => 'Open issues',
    ],
    'field' => [
        'project' => 'Project',
        'project_help' => 'Format namespace/project, e.g. gitlab-org/gitlab.',
        'project_placeholder' => 'namespace/project',
    ],
    'oauth' => [
        'instance_url' => 'GitLab instance URL',
        'instance_url_help' => 'Your GitLab root, e.g. https://gitlab.example.com. Leave blank for gitlab.com.',
        'client_id' => 'GitLab OAuth · Application ID',
        'client_id_help' => 'From GitLab → Preferences → Applications (scope: read_api).',
        'client_secret' => 'GitLab OAuth · Secret',
        'client_secret_help' => 'Leave blank to keep the stored value.',
    ],
];
