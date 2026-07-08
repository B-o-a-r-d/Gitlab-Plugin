<?php

namespace Board\PluginGitlab\Mcp;

use Board\PluginGitlab\GitLabClient;
use Board\PluginGitlab\GitLabPlugin;
use Board\PluginSdk\Contracts\PluginContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Lists recent commits of a project, using the GitLab Power-Up's stored token.
 * Fully decoupled from the host: board access + the (encrypted) config are
 * resolved through the SDK's PluginContext, which the host binds.
 */
#[Description('List recent commits of a GitLab project connected to a board through the GitLab Power-Up.')]
class GitlabCommitsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'project' => 'required|string',
        ]);

        $context = app(PluginContext::class);
        $boardId = (string) $request->get('board_id');

        if (! $context->userCanAccessBoard($boardId)) {
            return Response::error('Board not found or access denied.');
        }

        $config = $context->boardPluginConfig($boardId, 'gitlab');

        if ($config === null) {
            return Response::error('The GitLab Power-Up is not installed/active on this board.');
        }

        $commits = (new GitLabClient($config['token'] ?? null, GitLabPlugin::baseUrl($config)))
            ->recentCommits((string) $request->get('project'), 20);

        return Response::json([
            'commits' => collect($commits)->map(fn (array $commit): array => [
                'id' => $commit['id'] ?? null,
                'short_id' => $commit['short_id'] ?? null,
                'title' => $commit['title'] ?? null,
                'author' => $commit['author_name'] ?? null,
                'date' => $commit['created_at'] ?? null,
                'url' => $commit['web_url'] ?? null,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
            'project' => $schema->string()->description('The project, as namespace/project (or its numeric id).')->required(),
        ];
    }
}
