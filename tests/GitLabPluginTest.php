<?php

use Board\PluginGitlab\GitLabPlugin;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\Http;

function gitlabPlugin(): ProvidesListSource
{
    return app(PluginRegistry::class)->get('gitlab');
}

test('the plugin auto-registers into the host registry via its provider', function () {
    $plugin = app(PluginRegistry::class)->get('gitlab');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('GitLab')
        ->and($plugin->requiresOAuth())->toBeTrue()
        ->and($plugin->oauthProvider())->toBe('gitlab')
        ->and($plugin)->toBeInstanceOf(ProvidesListSource::class)
        ->and($plugin)->toBeInstanceOf(ProvidesMcpTools::class)
        ->and($plugin)->toBeInstanceOf(ProvidesOAuth::class);
});

test('the plugin ships its own file translations', function () {
    $plugin = gitlabPlugin();

    app()->setLocale('en');
    expect($plugin->description())->toBe('Read-only lists of a GitLab project\'s commits, merge requests and issues.')
        ->and(trans('gitlab::messages.mode.commits'))->toBe('Recent commits');

    app()->setLocale('fr');
    expect(trans('gitlab::messages.mode.commits'))->toBe('Derniers commits');
});

test('it declares the gitlab oauth endpoints and reads back the account', function () {
    $plugin = gitlabPlugin();

    expect($plugin->authorizeUrl())->toBe('https://gitlab.com/oauth/authorize')
        ->and($plugin->tokenUrl())->toBe('https://gitlab.com/oauth/token')
        ->and($plugin->scopes())->toBe(['read_api'])
        ->and($plugin->authorizeParameters())->toBe(['response_type' => 'code']);

    Http::fake(['gitlab.com/api/v4/user' => Http::response(['username' => 'octocat'])]);

    expect($plugin->resolveAccount('gl_token'))->toBe('octocat');
});

test('it targets a self-hosted instance via GITLAB_URL', function () {
    $_SERVER['GITLAB_URL'] = 'https://gl.example.test';

    try {
        expect(GitLabPlugin::baseUrl())->toBe('https://gl.example.test')
            ->and(gitlabPlugin()->authorizeUrl())->toBe('https://gl.example.test/oauth/authorize');
    } finally {
        unset($_SERVER['GITLAB_URL']);
    }
});

test('the per-board instance_url config overrides the base URL', function () {
    $plugin = gitlabPlugin();
    $config = ['instance_url' => 'https://gl.custom.test'];

    expect(GitLabPlugin::baseUrl($config))->toBe('https://gl.custom.test')
        ->and($plugin->authorizeUrl($config))->toBe('https://gl.custom.test/oauth/authorize')
        ->and($plugin->tokenUrl($config))->toBe('https://gl.custom.test/oauth/token');

    Http::fake(['gl.custom.test/api/v4/user' => Http::response(['username' => 'dev'])]);

    expect($plugin->resolveAccount('tok', $config))->toBe('dev');
});

test('config fields include the instance url, client id and secret', function () {
    $keys = array_column(gitlabPlugin()->configFields(), 'key');

    expect($keys)->toContain('instance_url')
        ->toContain('client_id')
        ->toContain('client_secret');
});

test('it maps recent commits into read-only list items', function () {
    Http::fake([
        'gitlab.com/api/v4/projects/*/repository/commits*' => Http::response([
            [
                'id' => 'abc123def456',
                'short_id' => 'abc123de',
                'title' => 'Fix the widget',
                'author_name' => 'Octo Cat',
                'created_at' => '2026-07-07T10:00:00Z',
                'web_url' => 'https://gitlab.com/o/r/-/commit/abc123def456',
            ],
        ]),
    ]);

    $items = gitlabPlugin()->items(['token' => 't'], 'commits', ['project' => 'o/r', 'limit' => 15]);

    expect($items)->toHaveCount(1)
        ->and($items->first()->title)->toBe('Fix the widget')
        ->and($items->first()->subtitle)->toBe('Octo Cat · abc123de')
        ->and($items->first()->externalRef)->toBe('abc123def456');
});

test('it maps open merge requests', function () {
    Http::fake([
        'gitlab.com/api/v4/projects/*/merge_requests*' => Http::response([
            ['iid' => 7, 'title' => 'Add feature', 'author' => ['username' => 'dev'], 'web_url' => 'https://gitlab.com/o/r/-/merge_requests/7', 'updated_at' => '2026-07-07T10:00:00Z', 'draft' => false],
        ]),
    ]);

    $item = gitlabPlugin()->items(['token' => 't'], 'merge_requests', ['project' => 'o/r'])->first();

    expect($item->title)->toBe('Add feature')
        ->and($item->subtitle)->toBe('!7 · dev')
        ->and($item->externalRef)->toBe('7')
        ->and($item->badge)->toBe('open');
});

test('it maps open issues', function () {
    Http::fake([
        'gitlab.com/api/v4/projects/*/issues*' => Http::response([
            ['iid' => 3, 'title' => 'A bug', 'author' => ['username' => 'dev'], 'web_url' => 'https://gitlab.com/o/r/-/issues/3', 'updated_at' => '2026-07-07T10:00:00Z', 'user_notes_count' => 2],
        ]),
    ]);

    $item = gitlabPlugin()->items(['token' => 't'], 'issues', ['project' => 'o/r'])->first();

    expect($item->title)->toBe('A bug')
        ->and($item->subtitle)->toBe('#3 · dev')
        ->and($item->externalRef)->toBe('3');
});

test('an empty project yields no items and no request', function () {
    Http::fake(['gitlab.com/api/v4/*' => Http::response([], 500)]);

    expect(gitlabPlugin()->items(['token' => 't'], 'commits', ['project' => ''])->all())->toBe([]);
});
