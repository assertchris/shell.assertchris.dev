<?php

namespace App\Http\Responders\Docs;

use App\Services\DocPagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShowDocPageResponder
{
    public function __construct(public DocPagesService $docPages) {}

    public function __invoke(Request $request, string $slug): View
    {
        $pages = $this->docPages->all();

        abort_unless(isset($pages[$slug]), 404);

        $path = resource_path('docs/'.$pages[$slug]['file']);

        return view('docs.show', [
            'title' => $pages[$slug]['title'],
            'content' => Str::markdown(file_get_contents($path)),
            'pages' => $this->docPages->titles(),
            'currentSlug' => $slug,
        ]);
    }
}
