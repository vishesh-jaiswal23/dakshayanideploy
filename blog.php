<?php

declare(strict_types=1);

require_once __DIR__ . '/server/helpers.php';
require_once __DIR__ . '/server/modules.php';

ensure_session();
server_bootstrap();

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$post = $id !== '' ? blog_post_find($id) : null;

if (!$post || ($post['status'] ?? 'draft') !== 'published') {
    http_response_code(404);
    $token = issue_csrf_token();
    $placeholder = blog_placeholder_image();
    $title = 'Blog post not found';
    $content = '<p>The requested blog article could not be located. It may have been moved or unpublished.</p>';
    $metaKeywords = [];
    $createdAt = null;
    $coverImage = '';
} else {
    $token = issue_csrf_token();
    $placeholder = blog_placeholder_image();
    $title = $post['title'] ?? 'Dakshayani blog';
    $content = $post['content'] ?? '';
    $metaKeywords = $post['keywords'] ?? [];
    $createdAt = $post['created_at'] ?? null;
    $coverImage = $post['cover_image'] ?? '';
}

function blog_cover(string $path, string $token, string $placeholder): string
{
    $file = $path !== '' ? $path : $placeholder;
    return '/download.php?file=' . rawurlencode($file) . '&token=' . rawurlencode($token) . '&inline=1';
}

function blog_format_date(?string $value): string
{
    if ($value === null) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('j F Y, g:i a');
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
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> | Dakshayani Enterprises</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($content), 0, 155), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
    <?php if (!empty($metaKeywords)): ?>
      <meta name="keywords" content="<?= htmlspecialchars(implode(', ', $metaKeywords), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
    <?php endif; ?>
    <link rel="stylesheet" href="/style.css" />
  </head>
  <body class="page-shell">
    <?php include __DIR__ . '/partials/header.html'; ?>
    <main class="page-main">
      <article class="article">
        <header class="article__header">
          <p class="article__meta">Published <?= htmlspecialchars(blog_format_date($createdAt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <h1 class="article__title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
          <?php if (!empty($metaKeywords)): ?>
            <p class="article__tags">Topics: <?= htmlspecialchars(implode(', ', $metaKeywords), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <?php endif; ?>
        </header>
        <figure class="article__figure">
          <img src="<?= htmlspecialchars(blog_cover($coverImage, $token, $placeholder), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> cover image" loading="lazy" />
        </figure>
        <section class="article__content">
          <?= $content; ?>
        </section>
        <footer class="article__footer">
          <a class="article__back" href="/blogs.php">← Back to Insights</a>
        </footer>
      </article>
    </main>
    <?php include __DIR__ . '/partials/footer.html'; ?>
  </body>
</html>
