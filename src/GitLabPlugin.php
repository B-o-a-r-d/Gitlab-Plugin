<?php

namespace Board\PluginGitlab;

use Board\PluginGitlab\Mcp\GitlabCommitsTool;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesAutomationActions;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\Contracts\ProvidesSettings;
use Board\PluginSdk\PluginListItem;
use Board\PluginSdk\PluginToast;
use Board\PluginSdk\Support\PluginSettings;
use Board\PluginSdk\Support\SafeUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * GitLab Power-Up: read-only lists (commits / merge requests / issues) fed from
 * a project, connected through OAuth, plus an MCP tool. All user-facing strings
 * come from this package's `gitlab::` translations.
 *
 * Works against gitlab.com by default, or a self-hosted instance via the
 * `GITLAB_URL` environment variable (one instance per Board deployment).
 */
class GitLabPlugin implements Plugin, ProvidesAutomationActions, ProvidesListSource, ProvidesMcpTools, ProvidesOAuth, ProvidesSettings
{
    /**
     * The GitLab instance root, shared by the OAuth endpoints, the API client and
     * the MCP tool. Precedence: the per-board `instance_url` config field (set by a
     * board admin) → the instance-wide `default_instance_url` setting (set by a
     * platform admin in the marketplace) → gitlab.com.
     *
     * SSRF defense in depth: the per-board `instance_url` is honoured only when it
     * passes {@see SafeUrl} (http/https, no private/reserved host) so a board admin
     * cannot point the server — and the OAuth token exchange that carries the
     * client secret — at cloud metadata or an internal service. Self-hosted GitLab
     * on a private network is permitted via the plugin's `allowed_hosts` setting.
     * The `default_instance_url` is trusted (platform-admin config, no board input),
     * so it is used as-is.
     *
     * @param  array<string, mixed>  $config
     */
    public static function baseUrl(array $config = []): string
    {
        $settings = PluginSettings::for(self::key());

        $configured = trim((string) ($config['instance_url'] ?? ''));
        $allowed = SafeUrl::parseHostList((string) $settings->get('allowed_hosts', ''));

        if ($configured !== '' && SafeUrl::isSafe($configured, $allowed)) {
            return rtrim($configured, '/');
        }

        $default = trim((string) $settings->get('default_instance_url', '')) ?: 'https://gitlab.com';

        return rtrim($default, '/');
    }

    public static function key(): string
    {
        return 'gitlab';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        return [
            [
                'key' => 'default_instance_url',
                'label' => __('gitlab::messages.settings.default_instance_url'),
                'type' => 'url',
                'required' => false,
                'placeholder' => 'https://gitlab.com',
                'help' => __('gitlab::messages.settings.default_instance_url_help'),
            ],
            [
                'key' => 'allowed_hosts',
                'label' => __('gitlab::messages.settings.allowed_hosts'),
                'type' => 'text',
                'required' => false,
                'placeholder' => 'gitlab.internal, 10.0.0.5',
                'help' => __('gitlab::messages.settings.allowed_hosts_help'),
            ],
        ];
    }

    public function label(): string
    {
        return 'GitLab';
    }

    public function description(): string
    {
        return __('gitlab::messages.description');
    }

    public function icon(): string
    {
        return 'gitlab-logo';
    }

    public function requiresOAuth(): bool
    {
        return true;
    }

    public function oauthProvider(): ?string
    {
        return 'gitlab';
    }

    public function configFields(array $config = []): array
    {
        return [
            [
                'key' => 'instance_url',
                'label' => __('gitlab::messages.oauth.instance_url'),
                'type' => 'url',
                'placeholder' => 'https://gitlab.com',
                'help' => __('gitlab::messages.oauth.instance_url_help'),
            ],
            [
                'key' => 'client_id',
                'label' => __('gitlab::messages.oauth.client_id'),
                'type' => 'text',
                'placeholder' => 'a1b2c3…',
                'help' => __('gitlab::messages.oauth.client_id_help'),
            ],
            [
                'key' => 'client_secret',
                'label' => __('gitlab::messages.oauth.client_secret'),
                'type' => 'password',
                'help' => __('gitlab::messages.oauth.client_secret_help'),
            ],
        ];
    }

    // --- ProvidesOAuth --------------------------------------------------------

    public function authorizeUrl(array $config = []): string
    {
        return self::baseUrl($config).'/oauth/authorize';
    }

    public function tokenUrl(array $config = []): string
    {
        return self::baseUrl($config).'/oauth/token';
    }

    public function scopes(): array
    {
        return ['read_api'];
    }

    public function authorizeParameters(): array
    {
        // Doorkeeper (GitLab) requires an explicit response_type on the authorize call.
        return ['response_type' => 'code'];
    }

    public function resolveAccount(string $accessToken, array $config = []): ?string
    {
        return $this->client(array_merge($config, ['token' => $accessToken]))->account()['username'] ?? null;
    }

    // --- ProvidesListSource ---------------------------------------------------

    public function sourceModes(): array
    {
        return [
            ['key' => 'commits', 'label' => __('gitlab::messages.mode.commits')],
            ['key' => 'merge_requests', 'label' => __('gitlab::messages.mode.merge_requests')],
            ['key' => 'issues', 'label' => __('gitlab::messages.mode.issues')],
        ];
    }

    public function listConfigFields(array $config = []): array
    {
        $projects = $this->client($config)->listProjects();

        if ($projects !== []) {
            return [[
                'key' => 'project',
                'label' => __('gitlab::messages.field.project'),
                'type' => 'select',
                'options' => array_map(fn (array $project): array => [
                    'value' => $project['path_with_namespace'],
                    'label' => $project['path_with_namespace'],
                    'icon' => $project['private'] ? 'lock-simple' : null,
                ], $projects),
            ]];
        }

        return [[
            'key' => 'project',
            'label' => __('gitlab::messages.field.project'),
            'type' => 'text',
            'placeholder' => __('gitlab::messages.field.project_placeholder'),
            'help' => __('gitlab::messages.field.project_help'),
        ]];
    }

    public function items(array $config, string $mode, array $sourceConfig): Collection
    {
        $project = trim((string) ($sourceConfig['project'] ?? ''));

        if ($project === '') {
            return collect();
        }

        $limit = max(1, (int) ($sourceConfig['limit'] ?? 15));
        $client = $this->client($config);

        return match ($mode) {
            'merge_requests' => $this->mapMergeRequests($client->openMergeRequests($project, $limit)),
            'issues' => $this->mapIssues($client->openIssues($project, $limit)),
            default => $this->mapCommits($client->recentCommits($project, $limit)),
        };
    }

    // --- ProvidesMcpTools -----------------------------------------------------

    public function mcpTools(): array
    {
        return [GitlabCommitsTool::class];
    }

    // --- internals ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     */
    // --- ProvidesAutomationActions ---------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function automationActions(): array
    {
        return [[
            'key' => 'create_issue',
            'label' => __('gitlab::messages.automation.create_issue'),
            'configFields' => [
                ['key' => 'project', 'label' => __('gitlab::messages.automation.project'), 'type' => 'text'],
                ['key' => 'title', 'label' => __('gitlab::messages.automation.title'), 'type' => 'text'],
                ['key' => 'body', 'label' => __('gitlab::messages.automation.body'), 'type' => 'text'],
                ['key' => 'labels', 'label' => __('gitlab::messages.automation.labels'), 'type' => 'text'],
            ],
        ]];
    }

    public function runAutomationAction(array $config, string $key, array $card, array $actionConfig): ?PluginToast
    {
        if ($key !== 'create_issue') {
            return null;
        }

        $project = trim((string) ($actionConfig['project'] ?? ''));

        if ($project === '') {
            throw new \RuntimeException('gitlab: project not configured (group/project or id).');
        }

        $replace = [
            '{card}' => (string) ($card['title'] ?? ''),
            '{board}' => (string) ($card['board'] ?? ''),
            '{list}' => (string) ($card['list'] ?? ''),
        ];

        $title = trim(strtr((string) ($actionConfig['title'] ?? ''), $replace))
            ?: ((string) ($card['title'] ?? '') ?: 'Board card');
        $body = trim(strtr((string) ($actionConfig['body'] ?? ''), $replace));
        $labels = array_values(array_filter(array_map(trim(...), explode(',', (string) ($actionConfig['labels'] ?? '')))));

        $issue = $this->client($config)->createIssue($project, $title, $body, $labels);

        $iid = (int) ($issue['iid'] ?? 0);
        $url = (string) ($issue['web_url'] ?? '');

        return new PluginToast(
            message: __('gitlab::messages.automation.issue_created'),
            description: $iid > 0 ? "{$project}#{$iid}" : $project,
            duration: 8000,
            actions: $url === '' ? [] : [
                ['label' => __('gitlab::messages.automation.open_issue'), 'url' => $url],
            ],
        );
    }

    private function client(array $config): GitLabClient
    {
        $base = self::baseUrl($config);

        // Pin the socket to the IP we vetted (allow-listed or public), defeating
        // a DNS rebind between the base-URL check and the actual request. When
        // the host can't be vetted (e.g. an admin's intentionally-internal
        // default_instance_url), leave it unpinned — same behaviour as before.
        $allowed = SafeUrl::parseHostList((string) PluginSettings::for(self::key())->get('allowed_hosts', ''));
        $pin = SafeUrl::safeConnection($base, $allowed);

        return new GitLabClient($config['token'] ?? null, $base, $pin);
    }

    /**
     * @param  array<int, array<string, mixed>>  $commits
     * @return Collection<int, PluginListItem>
     */
    private function mapCommits(array $commits): Collection
    {
        return collect($commits)->map(function (array $commit): PluginListItem {
            $id = (string) ($commit['id'] ?? '');
            $short = (string) ($commit['short_id'] ?? Str::substr($id, 0, 8));
            $author = (string) ($commit['author_name'] ?? '—');

            return new PluginListItem(
                externalRef: $id,
                title: (string) ($commit['title'] ?? $id),
                subtitle: $author.' · '.$short,
                url: (string) ($commit['web_url'] ?? ''),
                icon: 'git-commit',
                timestamp: (string) ($commit['created_at'] ?? ''),
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $mrs
     * @return Collection<int, PluginListItem>
     */
    private function mapMergeRequests(array $mrs): Collection
    {
        return collect($mrs)->map(function (array $mr): PluginListItem {
            $iid = (int) ($mr['iid'] ?? 0);
            $isDraft = (bool) ($mr['draft'] ?? $mr['work_in_progress'] ?? false);

            return new PluginListItem(
                externalRef: (string) $iid,
                title: (string) ($mr['title'] ?? ''),
                subtitle: '!'.$iid.' · '.(string) data_get($mr, 'author.username', '—'),
                url: (string) ($mr['web_url'] ?? ''),
                badge: $isDraft ? 'draft' : 'open',
                badgeColor: $isDraft ? 'neutral' : 'green',
                icon: 'git-merge',
                timestamp: (string) ($mr['updated_at'] ?? ''),
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return Collection<int, PluginListItem>
     */
    private function mapIssues(array $issues): Collection
    {
        return collect($issues)->map(function (array $issue): PluginListItem {
            $iid = (int) ($issue['iid'] ?? 0);

            return new PluginListItem(
                externalRef: (string) $iid,
                title: (string) ($issue['title'] ?? ''),
                subtitle: '#'.$iid.' · '.(string) data_get($issue, 'author.username', '—'),
                url: (string) ($issue['web_url'] ?? ''),
                icon: 'circle-dashed',
                timestamp: (string) ($issue['updated_at'] ?? ''),
            );
        });
    }
}
