<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the VLF app (resources/vlf/app.html) to signed-in users only.
 * The page lives outside public/ so it can't be opened without logging in; the
 * signed-in user and a CSRF token are injected so the page's scripts can call the API.
 */
class VlfAppController extends Controller
{
    public function show(Request $request): Response
    {
        $html = file_get_contents(resource_path('vlf/app.html'));

        $user = json_encode($request->user()->toClient(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $head = '<meta name="csrf-token" content="'.e(csrf_token()).'">'."\n"
            .'<script>window.VLF_USER = '.$user.'; window.VLF_LOGOUT = '.json_encode(route('logout')).';</script>'."\n";

        $html = preg_replace('~</head>~', $head.'</head>', $html, 1);

        // The host lets browsers keep js/css for a week; a version from the file's
        // modified time makes them fetch the new copy as soon as a file changes.
        $html = preg_replace_callback('~(src|href)="((?:js|css)/[\w.-]+\.(?:js|css))"~', function (array $m) {
            $path = public_path($m[2]);

            return is_file($path) ? $m[1].'="'.$m[2].'?v='.filemtime($path).'"' : $m[0];
        }, $html);

        return response($html)->header('Cache-Control', 'no-store, private');
    }
}
