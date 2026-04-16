<?php

use App\Mcp\Servers\ShellServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', ShellServer::class)->middleware('auth:sanctum');
