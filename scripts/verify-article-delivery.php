<?php
/** Public, cookie-free verification. Prints no article bodies or private data. */
$expect = $argv[1] ?? 'after';
if (!in_array($expect, ['before', 'after'], true)) { throw new RuntimeException('Invalid phase'); }
function ensure($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
function download_public($url) {
    $headers = [];
    $url = preg_replace_callback('/[^\x00-\x7F]/', fn($m) => rawurlencode($m[0]), $url);
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'RadioRubben-DeliveryCheck/1.0',
        CURLOPT_HEADERFUNCTION => function($c, $line) use (&$headers) {
            if (preg_match('/^(age|cache-control|x-cache|x-wp-cf-super-cache|x-proxy-cache|last-modified|etag):/i', $line)) $headers[] = trim($line);
            return strlen($line);
        }]);
    $body = curl_exec($c); $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $type = curl_getinfo($c, CURLINFO_CONTENT_TYPE); curl_close($c);
    ensure($status === 200 && is_string($body), 'HTTP verification failed: ' . $status);
    return [$body, $headers, $type];
}
$articles = [
    1594 => ['https://www.radiorubben.no/b-ruten-arsvagen-mortavika-innstilt-30-oktober-2026/', 'https://www.fjord1.no/trafikkmeldingar'],
    1601 => ['https://www.radiorubben.no/bomlo-ungdom-gratis-volleyballkveld/', 'https://bremnesil.no/bilnyheiter/2026/10/17/volleyballturnering-for-ungdommar-i-bomlohallen/'],
];
libxml_use_internal_errors(true);
foreach ($articles as $id => [$url, $source]) {
    foreach (['ordinary', 'fresh'] as $variant) {
        [$html, $headers, $type] = download_public($url . ($variant === 'fresh' ? '?rr_delivery=' . time() : ''));
        $dom = new DOMDocument(); $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); $x = new DOMXPath($dom);
        $article = $x->query('//article[contains(concat(" ", normalize-space(@class), " "), " rr-single-article ")]');
        ensure($article->length === 1, "$id: article root");
        $scope = $article->item(0);
        $notices = $x->query('.//aside[@class="rr-editorial-notice"]', $scope);
        ensure($notices->length === ($expect === 'after' ? 1 : 0), "$id: expected notice count for $expect");
        $content = $x->query('.//div[contains(concat(" ", @class, " "), " rr-article-content ")]', $scope)->item(0);
        ensure($content !== null, "$id: article content");
        if ($expect === 'after') {
            $first = $x->query('./*[1]', $content)->item(0);
            $second = $x->query('./*[2]', $content)->item(0);
            ensure($first && $second && $first->nodeName === 'p' && $second->isSameNode($notices->item(0)), "$id: notice must follow lead");
            ensure(!str_contains($html, 'class="rubben-ai-notice"'), "$id: old notice remains");
            ensure(!str_contains($content->textContent, 'Teksten er utarbeidet med kunstig intelligens på grunnlag av Bremnes ILs'), "$id: duplicate footer");
        }
        ensure($x->query('.//a[@href="' . $source . '"]', $content)->length > 0, "$id: source missing");
        ensure($x->query('//link[@rel="canonical" and @href="' . $url . '"]')->length === 1, "$id: canonical");
        ensure(str_contains($html, 'facebook.addEventListener') && str_contains($html, 'navigator.share(data)'), "$id: #107 script missing");
        $share = $x->query('.//aside[@class="rr-share"]', $scope)->item(0);
        ensure($share !== null, "$id: share component");
        $title = $share->getAttribute('data-share-title');
        ensure($title !== '', "$id: share title");
        $channelCount = 0;
        foreach ($x->query('.//a[@href]', $share) as $a) {
            $href = $a->getAttribute('href'); parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $q);
            if (str_starts_with($href, 'https://www.facebook.com/')) { ensure(($q['u'] ?? '') === $url, "$id: Facebook URL"); $channelCount++; }
            if (str_starts_with($href, 'https://wa.me/')) { ensure(($q['text'] ?? '') === "$title $url", "$id: WhatsApp data"); $channelCount++; }
            if (str_starts_with($href, 'mailto:')) { ensure(($q['subject'] ?? '') === $title && ($q['body'] ?? '') === "$title\n\n$url", "$id: mail data"); $channelCount++; }
        }
        ensure($channelCount === 4, "$id: channel links");
        ensure($x->query('.//input[@class="rr-share-fallback" and @value="' . $url . '"]', $share)->length === 1, "$id: copy value");
        $images = $x->query('.//div[contains(@class,"rr-single-image")]//img', $scope);
        ensure($images->length === 1, "$id: hero count");
        $image = $images->item(0); $imageUrl = $image->getAttribute('src');
        ensure(str_starts_with($imageUrl, 'https://www.radiorubben.no/wp-content/uploads/'), "$id: unexpected image origin");
        [$imageBody, $unused, $imageType] = download_public($imageUrl);
        $size = getimagesizefromstring($imageBody);
        ensure(str_starts_with($imageType, 'image/') && $size && $size[0] > 100, "$id: broken image");
        ensure($x->query('.//figcaption//a[@href]', $scope)->length > 0, "$id: image credit");
        echo json_encode(['post'=>$id,'phase'=>$expect,'variant'=>$variant,'notice_count'=>$notices->length,'sharing'=>'PR107','hero'=>[$size[0],$size[1]],'source'=>'preserved','cache_headers'=>$headers], JSON_UNESCAPED_SLASHES) . "\n";
    }
}
