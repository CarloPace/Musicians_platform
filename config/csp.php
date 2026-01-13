<?php 
//Content security policies 
//default-src 'self' = load resources only from the same origin of the page (external resources not allowed)
//script-src 'self' = load javascript only from the same origin of the page (external javascript not allowed)
//media-src 'self' = load medias only from the same origin of the page (external media not allowed)
//frame-ancestor 'none' = donn't allow the page to be iframed by anyone (preventing clickjacking)
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://cdnjs.cloudflare.com; media-src 'self'; frame-ancestors 'none'; img-src 'self' data:");

?>