<?php

namespace Board\PluginGitlab;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin GitLab REST (v4) client. Uses Laravel's HTTP client with conservative
 * timeouts. All calls are read-only and fail soft: on any error they return an
 * empty array (or null) so a list degrades to "no items" rather than throwing.
 *
 * Works against gitlab.com or a self-hosted instance — the base URL is injected.
 */
class GitLabClient
{
    /**
     * @param  string  $baseUrl  the GitLab instance root, e.g. https://gitlab.com
     * @param  array{host: string, ip: string, port: int}|null  $pin  vetted connection to pin the socket to (anti DNS-rebinding)
     */
    public function __construct(
        private readonly ?string $token,
        private readonly string $baseUrl = 'https://gitlab.com',
        private readonly ?array $pin = null,
    ) {}

    private function request(): PendingRequest
    {
        // Do not follow redirects: a 3xx from the instance could otherwise pivot
        // the request (and the bearer token) to an internal host (SSRF).
        $request = Http::baseUrl(rtrim($this->baseUrl, '/').'/api/v4')
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'BoardBot/1.0 (+plugin-gitlab)'])
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(8);

        // Pin the connection to the exact IP that was vetted, so a DNS rebind
        // between the check and the connect can't redirect us to an internal host.
        if ($this->pin !== null) {
            $request->withOptions([
                'curl' => [CURLOPT_RESOLVE => ["{$this->pin['host']}:{$this->pin['port']}:{$this->pin['ip']}"]],
            ]);
        }

        return $this->token ? $request->withToken($this->token) : $request;
    }

    /**
     * URL-encode a "namespace/project" path into the id segment GitLab expects
     * (e.g. "group/app" → "group%2Fapp"). A numeric id passes through untouched.
     */
    private function projectId(string $project): string
    {
        return rawurlencode(trim($project));
    }

    /**
     * Projects the connected account is a member of (for the dynamic picker).
     *
     * @return array<int, array{path_with_namespace: string, private: bool}>
     */
    public function listProjects(): array
    {
        $response = $this->request()->get('/projects', [
            'membership' => 'true',
            'simple' => 'true',
            'order_by' => 'last_activity_at',
            'per_page' => 100,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $project): array => [
                'path_with_namespace' => (string) ($project['path_with_namespace'] ?? ''),
                'private' => ($project['visibility'] ?? 'private') !== 'public',
            ])
            ->filter(fn (array $project): bool => $project['path_with_namespace'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentCommits(string $project, int $limit = 15): array
    {
        return $this->paged("/projects/{$this->projectId($project)}/repository/commits", [], $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openMergeRequests(string $project, int $limit = 15): array
    {
        return $this->paged("/projects/{$this->projectId($project)}/merge_requests", ['state' => 'opened', 'order_by' => 'updated_at'], $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openIssues(string $project, int $limit = 15): array
    {
        return $this->paged("/projects/{$this->projectId($project)}/issues", ['state' => 'opened', 'order_by' => 'updated_at'], $limit);
    }

    /**
     * Create an issue. Throws on HTTP failure so the host's automation
     * pipeline can count and journal the error.
     *
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    public function createIssue(string $project, string $title, string $description = '', array $labels = []): array
    {
        return $this->request()
            ->post('/projects/'.$this->projectId($project).'/issues', array_filter([
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'labels' => $labels !== [] ? implode(',', $labels) : null,
            ]))
            ->throw()
            ->json();
    }

    /**
     * The authenticated account, or null if the token is missing/invalid.
     *
     * @return array{username: string}|null
     */
    public function account(): ?array
    {
        $response = $this->request()->get('/user');

        return $response->successful() && isset($response->json()['username'])
            ? $response->json()
            : null;
    }

    /**
     * Fetch up to $limit items across pages (GitLab caps per_page at 100).
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paged(string $path, array $query, int $limit): array
    {
        $limit = max(1, $limit);
        $perPage = min(100, $limit);
        $items = [];

        for ($page = 1; $page <= 20 && count($items) < $limit; $page++) {
            $batch = $this->collection($path, array_merge($query, ['per_page' => $perPage, 'page' => $page]));

            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                $items[] = $item;
            }

            if (count($batch) < $perPage) {
                break;
            }
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function collection(string $path, array $query): array
    {
        $response = $this->request()->get($path, $query);

        return $response->successful() && is_array($response->json())
            ? $response->json()
            : [];
    }
}
