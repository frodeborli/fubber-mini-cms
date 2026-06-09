<?php

$aspect = $_GET[0] ?? '1x1';
$parts = preg_split('/[x:\/]/', $aspect, 2);
$w = max(1, (int)($parts[0] ?? 1));
$h = max(1, (int)($parts[1] ?? 1));

$viewW = 400;
$viewH = (int)round($viewW * $h / $w);
$cx = (int)($viewW / 2);
$cy = (int)($viewH / 2);

$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$viewW}" height="{$viewH}" viewBox="0 0 {$viewW} {$viewH}">
  <rect width="100%" height="100%" fill="#e5e7eb"/>
  <g transform="translate({$cx}, {$cy})" text-anchor="middle">
    <path d="M-20,-16 h40 a4,4 0 0,1 4,4 v24 a4,4 0 0,1-4,4 h-40 a4,4 0 0,1-4,-4 v-24 a4,4 0 0,1 4,-4z M-8,-4 a8,8 0 1,0 16,0 a8,8 0 1,0-16,0z M-20,16 l12,-12 l8,8 l12,-12 l12,12 v4 a4,4 0 0,1-4,4 h-36 a4,4 0 0,1-4,-4z" fill="#9ca3af" transform="translate(0,-8)"/>
    <text y="32" font-family="system-ui,sans-serif" font-size="14" fill="#9ca3af">{$w}:{$h}</text>
  </g>
</svg>
SVG;

return new \mini\Http\Message\Response(
    body: $svg,
    headers: [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]
);
