<?php

namespace App\Http\Controllers;

use App\Support\UrlPrefix;
use Illuminate\Http\Response;

class PwaAssetController extends Controller
{
    public function manifest(): Response
    {
        $data = json_decode((string) file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $base = UrlPrefix::basePath();
        $root = $base === '' ? '/' : $base.'/';

        $data['id'] = $root;
        $data['start_url'] = $root;
        $data['scope'] = $root;

        foreach ($data['icons'] as $i => $icon) {
            $src = (string) ($icon['src'] ?? '');
            if ($src !== '' && str_starts_with($src, '/')) {
                $data['icons'][$i]['src'] = $base.$src;
            }
        }

        return response(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function serviceWorker(): Response
    {
        $script = (string) file_get_contents(public_path('sw.js'));
        $allowed = UrlPrefix::basePath() === '' ? '/' : UrlPrefix::basePath().'/';

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => $allowed,
        ]);
    }

    public function offline(): Response
    {
        $html = (string) file_get_contents(public_path('offline.html'));
        $home = UrlPrefix::urlPath('/');
        $html = str_replace('href="/"', 'href="'.e($home).'"', $html);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
