<?php namespace x\youtube;

// Get best image resolution
function image(string $id) {
    $u = 'https://img.youtube.com/vi/' . $id;
    if (\is_file($q = \LOT . \D . 'cache' . \D . 'x.youtube' . \D . $id)) {
        return $u . '/' . (\file_get_contents($q) ?: '0') . '.jpg';
    }
    if (!\extension_loaded('curl')) {
        return $u . '/0.jpg';
    }
    foreach (['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault', 'default'] as $v) {
        $c = \curl_init($u . '/' . $v . '.jpg');
        \curl_setopt_array($c, [
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_NOBODY => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 3
        ]);
        \curl_exec($c);
        $status = \curl_getinfo($c, \CURLINFO_HTTP_CODE);
        \curl_close($c);
        if (200 === $status) {
            \save($q, $v, 0600);
            return $u . '/' . $v . '.jpg';
        }
    }
    return $u . '/0.jpg';
}

function m(string $v) {
    if ("" === $v || (0 !== \strpos($v, 'http://') && 0 !== \strpos($v, 'https://')) || \strcspn($v, " \n\r\t") !== \strlen($v)) {
        return;
    }
    $v = \trim(\explode('://', $v, 2)[1]);
    // `www.…`
    if (0 === \strpos($v, 'www.')) {
        $v = \substr($v, 4);
    }
    // `youtu.be/…`
    if (0 === \strpos($v, 'youtu.be/')) {
        $v = \substr($v, 9);
    // `youtube.com/…`
    } else if (0 === \strpos($v, 'youtube.com/')) {
        $v = \substr($v, 12);
    } else {
        return;
    }
    if ("" !== ($z = \strstr($v, '?') ?: \strstr($v, '&') ?: \strstr($v, '#') ?: "")) {
        $v = \substr($v, 0, -\strlen($z));
    }
    // `embed/{id}` or `v/{id}`
    if (0 === \strpos($v, 'embed/') || 0 === \strpos($v, 'v/')) {
        return [\substr(\strstr($v, '/'), 1), $z];
    }
    // `watch?v={id}`
    if (0 === \strpos($v . $z, 'watch?')) {
        if ($query = \strstr($z, '#', true)) {
            $hash = \substr($z, \strlen($query));
        }
        \parse_str(\substr($query ?: $z, 1), $q);
        $id = $q['v'] ?? false;
        unset($q['v']);
        $q = \http_build_query($q);
        return [$id, ($q ? '?' . $q : "") . ($hash ?? "")];
    }
    // `{id}`
    return [$v, $z];
}

function page__content($content) {
    if (!$content || false === \strpos($content, '</p>')) {
        return $content;
    }
    $r = "";
    foreach (\apart($content, [
        'pre',
        'code', // Must come after `pre`
        'kbd',
        'math',
        'script',
        'style',
        'textarea',
        'p' // Must come last
    ]) as $v) {
        if ('</p>' !== \substr($v[0], -4)) {
            $r .= $v[0];
            continue;
        }
        $link = $title = false;
        $raw = \trim(\substr($v[0], $v[2], $v[3]));
        if (0 === ($n = \strpos($raw, '<a')) && \strspn($raw, " \n\r\t", $n + 2) && '</a>' === \substr($raw, -4)) {
            $e = new \HTML($raw);
            $link = $e['href'];
            $title = $e['title'] ?? $e[1];
        } else if (0 === ($n = \strpos($raw, '<iframe')) && \strspn($raw, " \n\r\t", $n + 7) && '</iframe>' === \substr($raw, -9)) {
            $e = new \HTML($raw);
            $link = $e['src'];
            $title = $e['title'];
        } else {
            $link = $raw;
        }
        if (!$m = m($link)) {
            $r .= $v[0];
            continue;
        }
        $p = new \HTML(\substr($v[0], 0, $v[2]));
        $r .= new \HTML(\Hook::fire('y.youtube', [[
            'etc' => $m[1],
            'id' => $m[0],
            0 => $p[0],
            1 => [
                0 => ['img', false, [
                    'alt' => "",
                    'role' => 'none',
                    'src' => image($m[0]),
                    'style' => 'border: 0; border-radius: 0; box-shadow: none; display: block; height: auto; margin: 0; padding: 0; width: 100%;'
                ]],
                1 => ['iframe', "", [
                    'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                    'allowfullscreen' => true,
                    'frameborder' => '0',
                    'src' => 'https://www.youtube.com/embed/' . $m[0] . $m[1],
                    'style' => 'border: 0; display: block; height: 100%; left: 0; margin: 0; overflow: hidden; padding: 0; position: absolute; top: 0; width: 100%;',
                    'title' => $title ?: \i('YouTube Video Player')
                ]]
            ],
            2 => \array_replace([
                'style' => 'display: block; margin-left: 0; margin-right: 0; overflow: hidden; padding: 0; position: relative;'
            ], (array) ($p[2] ?? []))
        ]]), true);
    }
    return "" !== $r ? $r : null;
}

\Hook::set('page.content', __NAMESPACE__ . "\\page__content", 2.1);