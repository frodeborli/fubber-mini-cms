<header>
    <nav>
        <a href="/"><?= \mini\h($siteName) ?></a>
        <ul>
            <?php foreach ($nav as $item): ?>
            <li><a href="<?= \mini\h($item['url']) ?>"<?= ($item['active'] ?? false) ? ' class="active"' : '' ?>><?= \mini\h($item['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
