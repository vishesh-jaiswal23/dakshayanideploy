<?php

declare(strict_types=1);

require_once __DIR__ . '/server/helpers.php';
require_once __DIR__ . '/server/modules.php';

ensure_session();
server_bootstrap();

$cacheKey = 'blog_list_cache';
$now = time();
$cached = $_SESSION[$cacheKey] ?? null;
if (!is_array($cached) || ($cached['expires'] ?? 0) < $now) {
    $posts = blog_posts_list(['status' => 'published']);
    $_SESSION[$cacheKey] = [
        'expires' => $now + 300,
        'data' => $posts,
    ];
} else {
    $posts = $cached['data'] ?? [];
}

$token = issue_csrf_token();
$placeholder = blog_placeholder_image();

function blog_cover_url(array $post, string $token, string $placeholder): string
{
    $path = $post['cover_image'] ?? '';
    if ($path === '') {
        $path = $placeholder;
    }
    if (str_starts_with($path, 'download.php')) {
        $url = '/' . ltrim($path, '/');
        if (str_contains($url, 'type=blog_image')) {
            $glue = str_contains($url, '?') ? '&' : '?';
            return $url . $glue . 'inline=1';
        }
        $glue = str_contains($url, '?') ? '&' : '?';
        return $url . $glue . 'token=' . rawurlencode($token) . '&inline=1';
    }
    return '/download.php?file=' . rawurlencode($path) . '&token=' . rawurlencode($token) . '&inline=1';
}

function blog_excerpt(array $post, int $limit = 220): string
{
    $text = trim(strip_tags($post['content'] ?? ''));
    if ($text === '') {
        return 'Fresh insights on sustainable energy and policy updates from Dakshayani Enterprises.';
    }
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return mb_substr($text, 0, $limit) . '…';
}

function format_date(string $value): string
{
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('j M Y, g:i a');
    } catch (Exception $exception) {
        return $value;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dakshayani Enterprises | Insights &amp; Blog</title>
    <meta name="description" content="Explore the latest blog posts from Dakshayani Enterprises covering solar EPC best practices, PM Surya Ghar updates, financing tips, and innovation stories." />
    <link rel="stylesheet" href="/style.css" />
  </head>
  <body class="page-shell">
    <?php include __DIR__ . '/partials/header.html'; ?>
    <main class="page-main">
      <section class="hero hero--compact">
        <div class="hero__content">
          <h1>Insights &amp; Blog</h1>
          <p>Curated intelligence on solar EPC, net-zero pathways, and customer success stories across India.</p>
        </div>
      </section>
      <section class="section section--light">
        <div class="section__content">
          <div class="blog-grid">
            <?php foreach ($posts as $post): ?>
              <article class="blog-card" data-blog-card>
                <a href="/blog.php?id=<?= urlencode($post['id']); ?>" class="blog-card__link" aria-label="Read <?= htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <img src="<?= htmlspecialchars(blog_cover_url($post, $token, $placeholder), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> cover image" loading="lazy" />
                  <div class="blog-card-body">
                    <span class="blog-card-meta">Published <?= htmlspecialchars(format_date($post['created_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                    <h3><?= htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>
                    <p><?= htmlspecialchars(blog_excerpt($post), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?>
              <div class="blog-card blog-card--empty">
                <div class="blog-card-body">
                  <span class="blog-card-meta">Stay tuned</span>
                  <h3>New stories are on the way</h3>
                  <p>Our sustainability analysts are preparing the next round of insights. Please check back shortly.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/partials/footer.html'; ?>
  </body>
</html>
