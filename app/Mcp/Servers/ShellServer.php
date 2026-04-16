<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListSlackChannelsTool;
use App\Mcp\Tools\ReadSlackMessagesTool;
use App\Mcp\Tools\SendSlackMessageTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Shell Server')]
#[Version('0.0.1')]
#[Instructions('This MCP server lets the AI agent interact with Shell — listing Slack channels, reading Slack messages, and more.')]
class ShellServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListSlackChannelsTool::class,
        ReadSlackMessagesTool::class,
        SendSlackMessageTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
