# GitLab Power-Up for Board

Read-only **GitLab** lists (recent commits, open merge requests, open issues) fed
from a project, connected through OAuth — a [Board](https://github.com/B-o-a-r-d/board)
Power-Up built on [`board/plugin-sdk`](https://github.com/B-o-a-r-d/Board-Plugin-SDK).

## Capabilities

- **List sources** — a board list fed by a project's commits / MRs / issues, rendered
  as read-only cards, lazy-loaded and refreshed live.
- **OAuth** — connect a GitLab OAuth application (credentials stored encrypted per
  board); the host drives the flow, the plugin only declares the endpoints.
- **MCP tool** — `gitlab-commits` lists a project's recent commits for an AI agent.

## Self-hosted GitLab

Defaults to `gitlab.com`. For a self-hosted instance, set one env var on the Board
deployment (one GitLab per instance):

```dotenv
GITLAB_URL=https://gitlab.example.com
```

It is used for both the OAuth endpoints and the API base.

## Install

Via the Board **Marketplace** (admin → `/marketplace`), or `composer require board/plugin-gitlab`.
Then, on a board: **Options → Power-Ups → GitLab**, configure the OAuth app
(Application ID + Secret, scope `read_api`, callback `/plugins/oauth/callback`),
connect, and create a list.

## Development

```bash
composer install
vendor/bin/pest
```
