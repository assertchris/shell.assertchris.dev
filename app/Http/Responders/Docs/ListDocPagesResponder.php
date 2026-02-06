<?php

namespace App\Http\Responders\Docs;

use App\Services\DocPagesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListDocPagesResponder
{
    public function __construct(public DocPagesService $docPages) {}

    public function __invoke(Request $request): View
    {
        return view('docs.index', [
            'pages' => $this->docPages->titles(),
        ]);
    }
}
