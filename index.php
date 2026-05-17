<?php
declare(strict_types=1);

session_start();

$dataFile = __DIR__ . '/links.json';
$statsFile = __DIR__ . '/stats.json';
$adminPassword = '1024';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateLinkId(): string
{
    return bin2hex(random_bytes(8));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function createInitialLinks(): array
{
    return [
        [
            'id' => generateLinkId(),
            'name' => '示例服务 8080',
            'url' => 'http://xfxuezhang.cn:8080',
            'desc' => '端口 8080 服务入口',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateLinkId(),
            'name' => '示例服务 8081',
            'url' => 'http://xfxuezhang.cn:8081',
            'desc' => '端口 8081 服务入口',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateLinkId(),
            'name' => '示例服务 8085',
            'url' => 'http://xfxuezhang.cn:8085',
            'desc' => '端口 8085 服务入口',
            'created_at' => date('Y-m-d H:i:s')
        ],
    ];
}

function loadLinks(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function saveLinks(string $file, array $links): bool
{
    $json = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json, LOCK_EX) !== false;
}

function normalizeLinks(array &$links): bool
{
    $changed = false;

    foreach ($links as &$item) {
        if (!is_array($item)) {
            $item = [];
            $changed = true;
        }

        if (empty($item['id'])) {
            $item['id'] = generateLinkId();
            $changed = true;
        }

        if (!isset($item['name'])) {
            $item['name'] = '';
            $changed = true;
        }

        if (!isset($item['url'])) {
            $item['url'] = '';
            $changed = true;
        }

        if (!isset($item['desc'])) {
            $item['desc'] = '';
            $changed = true;
        }

        if (!isset($item['created_at'])) {
            $item['created_at'] = date('Y-m-d H:i:s');
            $changed = true;
        }
    }

    unset($item);

    return $changed;
}

function findLinkIndexById(array $links, string $id): ?int
{
    foreach ($links as $index => $item) {
        if ((string)($item['id'] ?? '') === $id) {
            return $index;
        }
    }

    return null;
}

function loadStats(string $file): array
{
    if (!file_exists($file)) {
        return [
            'total_views' => 0,
            'last_visit_at' => ''
        ];
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return [
            'total_views' => 0,
            'last_visit_at' => ''
        ];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        return [
            'total_views' => 0,
            'last_visit_at' => ''
        ];
    }

    return [
        'total_views' => (int)($data['total_views'] ?? 0),
        'last_visit_at' => (string)($data['last_visit_at'] ?? '')
    ];
}

function saveStats(string $file, array $stats): bool
{
    $json = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json, LOCK_EX) !== false;
}

function redirectWithSuccess(string $code): void
{
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . '?success=' . urlencode($code));
    exit;
}

if (!file_exists($dataFile)) {
    saveLinks($dataFile, createInitialLinks());
}

$links = loadLinks($dataFile);

if (normalizeLinks($links)) {
    saveLinks($dataFile, $links);
}

$isLoggedIn = !empty($_SESSION['hub_logged_in']);
$csrfToken = (string)$_SESSION['csrf_token'];

$message = '';
$error = '';
$failedAction = '';

$formState = [
    'action' => 'add',
    'id' => '',
    'name' => '',
    'url' => '',
    'desc' => ''
];

$deleteState = [
    'id' => '',
    'name' => ''
];

$orderState = [
    'order' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $failedAction = $action;

    if (!hash_equals($csrfToken, $postedCsrf)) {
        $error = '请求已过期，请刷新页面后重试。';
    } elseif ($action === 'login') {
        $password = trim((string)($_POST['password'] ?? ''));

        if ($password === $adminPassword) {
            session_regenerate_id(true);
            $_SESSION['hub_logged_in'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            redirectWithSuccess('login');
        }

        $error = '登录失败。';
    } elseif ($action === 'logout') {
        unset($_SESSION['hub_logged_in']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        session_regenerate_id(true);
        redirectWithSuccess('logout');
    } elseif (!$isLoggedIn) {
        $error = '请先登录后再操作。';
    } elseif ($action === 'add' || $action === 'edit') {
        $formState = [
            'action' => $action,
            'id' => trim((string)($_POST['id'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'url' => trim((string)($_POST['url'] ?? '')),
            'desc' => trim((string)($_POST['desc'] ?? ''))
        ];

        if ($formState['name'] === '') {
            $error = '网站名不能为空。';
        } elseif ($formState['url'] === '') {
            $error = '网站地址不能为空。';
        } elseif (!filter_var($formState['url'], FILTER_VALIDATE_URL)) {
            $error = '网站地址格式不正确，请填写完整地址，例如 http://xfxuezhang.cn:8080。';
        } elseif ($action === 'add') {
            $links[] = [
                'id' => generateLinkId(),
                'name' => $formState['name'],
                'url' => $formState['url'],
                'desc' => $formState['desc'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (saveLinks($dataFile, $links)) {
                redirectWithSuccess('add');
            }

            $error = '保存失败，请检查 links.json 或当前目录是否有写入权限。';
        } else {
            $index = findLinkIndexById($links, $formState['id']);

            if ($index === null) {
                $error = '要编辑的入口不存在。';
            } else {
                $links[$index]['name'] = $formState['name'];
                $links[$index]['url'] = $formState['url'];
                $links[$index]['desc'] = $formState['desc'];
                $links[$index]['updated_at'] = date('Y-m-d H:i:s');

                if (saveLinks($dataFile, $links)) {
                    redirectWithSuccess('edit');
                }

                $error = '保存失败，请检查 links.json 或当前目录是否有写入权限。';
            }
        }
    } elseif ($action === 'delete') {
        $deleteState['id'] = trim((string)($_POST['id'] ?? ''));
        $index = findLinkIndexById($links, $deleteState['id']);

        if ($index === null) {
            $error = '要删除的入口不存在。';
        } else {
            $deleteState['name'] = (string)($links[$index]['name'] ?? '');
            array_splice($links, $index, 1);

            if (saveLinks($dataFile, $links)) {
                redirectWithSuccess('delete');
            }

            $error = '删除失败，请检查 links.json 是否有写入权限。';
        }
    } elseif ($action === 'reorder') {
        $orderState['order'] = trim((string)($_POST['order'] ?? ''));
        $order = json_decode($orderState['order'], true);

        if (!is_array($order)) {
            $error = '排序数据格式不正确。';
        } else {
            $linkMap = [];

            foreach ($links as $item) {
                $linkMap[(string)($item['id'] ?? '')] = $item;
            }

            $newLinks = [];

            foreach ($order as $id) {
                $id = (string)$id;

                if (isset($linkMap[$id])) {
                    $newLinks[] = $linkMap[$id];
                    unset($linkMap[$id]);
                }
            }

            foreach ($links as $item) {
                $id = (string)($item['id'] ?? '');

                if (isset($linkMap[$id])) {
                    $newLinks[] = $item;
                    unset($linkMap[$id]);
                }
            }

            $links = $newLinks;

            if (saveLinks($dataFile, $links)) {
                redirectWithSuccess('reorder');
            }

            $error = '排序保存失败，请检查 links.json 是否有写入权限。';
        }
    } else {
        $error = '未知操作。';
    }
}

if (isset($_GET['success'])) {
    $success = (string)$_GET['success'];

    if ($success === 'login') {
        $message = '已登录管理模式。';
    } elseif ($success === 'logout') {
        $message = '已退出管理模式。';
    } elseif ($success === 'add') {
        $message = '添加成功。';
    } elseif ($success === 'edit') {
        $message = '编辑成功。';
    } elseif ($success === 'delete') {
        $message = '删除成功。';
    } elseif ($success === 'reorder') {
        $message = '排序已保存。';
    }
}

$stats = loadStats($statsFile);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stats['total_views']++;
    $stats['last_visit_at'] = date('Y-m-d H:i:s');
    saveStats($statsFile, $stats);
}

$openLoginModal = $error !== '' && $failedAction === 'login';
$openManageModal = $isLoggedIn && $error !== '' && ($failedAction === 'add' || $failedAction === 'edit');
$openDeleteModal = $isLoggedIn && $error !== '' && $failedAction === 'delete';
$openOrderModal = $isLoggedIn && $error !== '' && $failedAction === 'reorder';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>服务入口 Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 32%),
                linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            color: #1f2937;
        }

        .page {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 34px 0 64px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 34px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .logo {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 800;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.26);
        }

        .brand-text {
            min-width: 0;
        }

        .title {
            margin: 0;
            font-size: 25px;
            line-height: 1.25;
            letter-spacing: -0.03em;
        }

        .subtitle {
            margin: 4px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .logout-form {
            margin: 0;
        }

        .add-button,
        .sort-save-button,
        .secondary-button {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: transform 0.18s ease, background 0.18s ease;
        }

        .add-button {
            background: #111827;
            box-shadow: 0 10px 24px rgba(17, 24, 39, 0.16);
        }

        .add-button:hover {
            background: #000000;
            transform: translateY(-1px);
        }

        .sort-save-button {
            background: #2563eb;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
        }

        .sort-save-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .sort-save-button[hidden] {
            display: none;
        }

        .secondary-button {
            background: #6b7280;
        }

        .secondary-button:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }

        .notice {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 14px;
        }

        .notice.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .notice.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }

        .card-item {
            position: relative;
            min-height: 154px;
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .card-item.dragging {
            opacity: 0.45;
            transform: scale(0.98);
        }

        .card-toolbar {
            position: absolute;
            z-index: 2;
            top: 12px;
            left: 12px;
            right: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            pointer-events: none;
        }

        .drag-handle,
        .mini-button {
            pointer-events: auto;
            border: 1px solid rgba(209, 213, 219, 0.9);
            background: rgba(255, 255, 255, 0.92);
            color: #374151;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        }

        .drag-handle {
            width: 34px;
            height: 30px;
            display: grid;
            place-items: center;
            cursor: grab;
            user-select: none;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .card-actions {
            display: flex;
            gap: 6px;
            opacity: 0.42;
            transition: opacity 0.18s ease;
        }

        .card-item:hover .card-actions,
        .card-item:focus-within .card-actions {
            opacity: 1;
        }

        .mini-button {
            padding: 6px 10px;
        }

        .mini-button:hover {
            background: #f3f4f6;
        }

        .mini-button.danger {
            color: #b91c1c;
        }

        .card {
            display: flex;
            flex-direction: column;
            min-height: 154px;
            height: 100%;
            padding: 20px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.88);
            text-decoration: none;
            color: inherit;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(229, 231, 235, 0.86);
            backdrop-filter: blur(12px);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .card.card-admin {
            padding-top: 48px;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.13);
            border-color: #bfdbfe;
        }

        .card-title {
            margin: 0 0 10px;
            font-size: 19px;
            font-weight: 800;
            color: #111827;
            word-break: break-word;
        }

        .card-desc {
            margin: 0 0 18px;
            color: #6b7280;
            line-height: 1.65;
            font-size: 14px;
            word-break: break-word;
        }

        .card-url {
            margin-top: auto;
            color: #2563eb;
            font-size: 13px;
            word-break: break-all;
        }

        .empty {
            padding: 32px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.86);
            color: #6b7280;
            border: 1px dashed #cbd5e1;
            text-align: center;
        }

        .footer {
            margin-top: 34px;
            padding: 18px 0 0;
            display: flex;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
            color: #6b7280;
            font-size: 13px;
        }

        .footer span {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(229, 231, 235, 0.9);
        }

        .modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .modal.show {
            display: flex;
        }

        .modal-mask {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.54);
            backdrop-filter: blur(6px);
        }

        .modal-card {
            position: relative;
            width: min(430px, 100%);
            border-radius: 24px;
            background: #ffffff;
            padding: 22px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
            animation: popIn 0.18s ease;
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .modal-title {
            margin: 0;
            font-size: 20px;
            color: #111827;
        }

        .modal-desc {
            margin: -4px 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.65;
        }

        .close-button {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 12px;
            background: #f3f4f6;
            color: #374151;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }

        .close-button:hover {
            background: #e5e7eb;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-size: 14px;
            font-weight: 700;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 12px 13px;
            font-size: 14px;
            outline: none;
            background: #ffffff;
        }

        input:focus,
        textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }

        textarea {
            min-height: 88px;
            resize: vertical;
        }

        .submit-button,
        .delete-submit-button {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 13px 16px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.18s ease, transform 0.18s ease;
        }

        .submit-button {
            background: #2563eb;
        }

        .submit-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .delete-submit-button {
            background: #dc2626;
        }

        .delete-submit-button:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .delete-name {
            font-weight: 800;
            color: #111827;
        }

        @media (max-width: 640px) {
            .page {
                width: min(100% - 24px, 1180px);
                padding-top: 22px;
            }

            .topbar {
                align-items: flex-start;
            }

            .title {
                font-size: 21px;
            }

            .subtitle {
                display: none;
            }

            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .add-button,
            .sort-save-button,
            .secondary-button {
                padding: 9px 13px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="brand">
            <div class="logo">Hub</div>
            <div class="brand-text">
                <h1 class="title">服务入口 Hub</h1>
                <p class="subtitle">xfxuezhang.cn 站点与端口服务导航</p>
            </div>
        </div>

        <div class="top-actions">
            <?php if ($isLoggedIn): ?>
                <button class="sort-save-button" type="button" id="saveOrderButton" hidden>保存排序</button>
                <button class="add-button" type="button" id="openAddModal">添加入口</button>

                <form class="logout-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="secondary-button" type="submit">退出登录</button>
                </form>
            <?php else: ?>
                <button class="add-button" type="button" id="openLoginModal">管理登录</button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="notice success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== '' && !$openLoginModal && !$openManageModal && !$openDeleteModal && !$openOrderModal): ?>
        <div class="notice error"><?= h($error) ?></div>
    <?php endif; ?>

    <main>
        <?php if (count($links) === 0): ?>
            <div class="empty">暂无服务入口。</div>
        <?php else: ?>
            <div class="grid" id="serviceGrid">
                <?php foreach ($links as $item): ?>
                    <?php
                    $id = (string)($item['id'] ?? '');
                    $name = (string)($item['name'] ?? '');
                    $url = (string)($item['url'] ?? '');
                    $desc = (string)($item['desc'] ?? '');
                    ?>
                    <div class="card-item" data-id="<?= h($id) ?>">
                        <?php if ($isLoggedIn): ?>
                            <div class="card-toolbar">
                                <button class="drag-handle" type="button" draggable="true" title="拖拽排序" aria-label="拖拽排序">⋮⋮</button>

                                <div class="card-actions">
                                    <button
                                        class="mini-button edit-button"
                                        type="button"
                                        data-id="<?= h($id) ?>"
                                        data-name="<?= h($name) ?>"
                                        data-url="<?= h($url) ?>"
                                        data-desc="<?= h($desc) ?>"
                                    >编辑</button>

                                    <button
                                        class="mini-button danger delete-button"
                                        type="button"
                                        data-id="<?= h($id) ?>"
                                        data-name="<?= h($name) ?>"
                                    >删除</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <a class="card<?= $isLoggedIn ? ' card-admin' : '' ?>" href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer">
                            <h3 class="card-title"><?= h($name) ?></h3>
                            <p class="card-desc"><?= h($desc !== '' ? $desc : '暂无简介') ?></p>
                            <div class="card-url"><?= h($url) ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <span>访问量 <?= number_format((int)$stats['total_views']) ?></span>
        <?php if (!empty($stats['last_visit_at'])): ?>
            <span>最近访问 <?= h((string)$stats['last_visit_at']) ?></span>
        <?php endif; ?>
    </footer>
</div>

<div class="modal<?= $openLoginModal ? ' show' : '' ?>" id="loginModal">
    <div class="modal-mask" data-close-modal="loginModal"></div>

    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
        <div class="modal-header">
            <h2 class="modal-title" id="loginModalTitle">管理登录</h2>
            <button class="close-button" type="button" data-close-modal="loginModal" aria-label="关闭">×</button>
        </div>

        <?php if ($openLoginModal && $error !== ''): ?>
            <div class="notice error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label for="loginPassword">登录密码</label>
                <input id="loginPassword" name="password" type="password" required placeholder="请输入登录密码">
            </div>

            <button class="submit-button" type="submit">登录</button>
        </form>
    </div>
</div>

<?php if ($isLoggedIn): ?>
    <div class="modal<?= $openManageModal ? ' show' : '' ?>" id="manageModal">
        <div class="modal-mask" data-close-modal="manageModal"></div>

        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="manageModalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="manageModalTitle"><?= $formState['action'] === 'edit' ? '编辑入口' : '添加入口' ?></h2>
                <button class="close-button" type="button" data-close-modal="manageModal" aria-label="关闭">×</button>
            </div>

            <?php if ($openManageModal && $error !== ''): ?>
                <div class="notice error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" id="manageAction" value="<?= h($formState['action']) ?>">
                <input type="hidden" name="id" id="manageId" value="<?= h($formState['id']) ?>">

                <div class="form-group">
                    <label for="name">网站名</label>
                    <input id="name" name="name" type="text" required placeholder="例如 Nas 管理后台" value="<?= h($formState['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="url">网站地址</label>
                    <input id="url" name="url" type="url" required placeholder="例如 http://xfxuezhang.cn:8080" value="<?= h($formState['url']) ?>">
                </div>

                <div class="form-group">
                    <label for="desc">网站简介</label>
                    <textarea id="desc" name="desc" placeholder="简单描述这个服务的用途"><?= h($formState['desc']) ?></textarea>
                </div>

                <button class="submit-button" type="submit">提交</button>
            </form>
        </div>
    </div>

    <div class="modal<?= $openDeleteModal ? ' show' : '' ?>" id="deleteModal">
        <div class="modal-mask" data-close-modal="deleteModal"></div>

        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="deleteModalTitle">删除入口</h2>
                <button class="close-button" type="button" data-close-modal="deleteModal" aria-label="关闭">×</button>
            </div>

            <?php if ($openDeleteModal && $error !== ''): ?>
                <div class="notice error"><?= h($error) ?></div>
            <?php endif; ?>

            <p class="modal-desc">
                确认删除 <span class="delete-name" id="deleteNameText"><?= h($deleteState['name']) ?></span> 吗？删除后需要重新添加。
            </p>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="<?= h($deleteState['id']) ?>">

                <button class="delete-submit-button" type="submit">确认删除</button>
            </form>
        </div>
    </div>

    <div class="modal<?= $openOrderModal ? ' show' : '' ?>" id="orderModal">
        <div class="modal-mask" data-close-modal="orderModal"></div>

        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="orderModalTitle">保存排序</h2>
                <button class="close-button" type="button" data-close-modal="orderModal" aria-label="关闭">×</button>
            </div>

            <?php if ($openOrderModal && $error !== ''): ?>
                <div class="notice error"><?= h($error) ?></div>
            <?php endif; ?>

            <p class="modal-desc">保存当前拖拽后的入口顺序。</p>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order" id="orderValue" value="<?= h($orderState['order']) ?>">

                <button class="submit-button" type="submit">保存排序</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    const loginModal = document.getElementById('loginModal');
    const manageModal = document.getElementById('manageModal');
    const deleteModal = document.getElementById('deleteModal');
    const orderModal = document.getElementById('orderModal');

    const openLoginModal = document.getElementById('openLoginModal');
    const openAddModal = document.getElementById('openAddModal');

    const manageModalTitle = document.getElementById('manageModalTitle');
    const manageAction = document.getElementById('manageAction');
    const manageId = document.getElementById('manageId');
    const nameInput = document.getElementById('name');
    const urlInput = document.getElementById('url');
    const descInput = document.getElementById('desc');

    const deleteId = document.getElementById('deleteId');
    const deleteNameText = document.getElementById('deleteNameText');

    const orderValue = document.getElementById('orderValue');
    const saveOrderButton = document.getElementById('saveOrderButton');
    const grid = document.getElementById('serviceGrid');

    function showModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('show');

        const firstInput = modal.querySelector('input:not([type="hidden"]), textarea');

        if (firstInput) {
            setTimeout(() => firstInput.focus(), 80);
        }
    }

    function hideModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
    }

    function hideAllModals() {
        [loginModal, manageModal, deleteModal, orderModal].forEach(function (modal) {
            hideModal(modal);
        });
    }

    if (openLoginModal) {
        openLoginModal.addEventListener('click', function () {
            showModal(loginModal);
        });
    }

    if (openAddModal) {
        openAddModal.addEventListener('click', function () {
            manageModalTitle.textContent = '添加入口';
            manageAction.value = 'add';
            manageId.value = '';
            nameInput.value = '';
            urlInput.value = '';
            descInput.value = '';
            showModal(manageModal);
        });
    }

    document.querySelectorAll('.edit-button').forEach(function (button) {
        button.addEventListener('click', function () {
            manageModalTitle.textContent = '编辑入口';
            manageAction.value = 'edit';
            manageId.value = button.dataset.id || '';
            nameInput.value = button.dataset.name || '';
            urlInput.value = button.dataset.url || '';
            descInput.value = button.dataset.desc || '';
            showModal(manageModal);
        });
    });

    document.querySelectorAll('.delete-button').forEach(function (button) {
        button.addEventListener('click', function () {
            deleteId.value = button.dataset.id || '';
            deleteNameText.textContent = button.dataset.name || '';
            showModal(deleteModal);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (element) {
        element.addEventListener('click', function () {
            const modalId = element.getAttribute('data-close-modal');
            const modal = document.getElementById(modalId);

            hideModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideAllModals();
        }
    });

    function collectOrder() {
        if (!grid) {
            return [];
        }

        return Array.from(grid.querySelectorAll('.card-item')).map(function (item) {
            return item.dataset.id;
        });
    }

    let originalOrder = collectOrder().join(',');
    let draggingItem = null;

    function refreshOrderButton() {
        if (!saveOrderButton) {
            return;
        }

        const currentOrder = collectOrder().join(',');
        saveOrderButton.hidden = currentOrder === originalOrder;
    }

    function getClosestItem(container, x, y) {
        const items = Array.from(container.querySelectorAll('.card-item:not(.dragging)'));

        if (items.length === 0) {
            return null;
        }

        let closest = null;

        items.forEach(function (item) {
            const box = item.getBoundingClientRect();
            const centerX = box.left + box.width / 2;
            const centerY = box.top + box.height / 2;
            const dx = x - centerX;
            const dy = y - centerY;
            const distance = Math.sqrt(dx * dx + dy * dy);

            if (!closest || distance < closest.distance) {
                closest = {
                    element: item,
                    distance: distance,
                    before: y < centerY || (Math.abs(y - centerY) < box.height / 2 && x < centerX)
                };
            }
        });

        return closest;
    }

    if (isLoggedIn && grid) {
        document.querySelectorAll('.drag-handle').forEach(function (handle) {
            handle.addEventListener('dragstart', function (event) {
                draggingItem = handle.closest('.card-item');

                if (!draggingItem) {
                    return;
                }

                draggingItem.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', draggingItem.dataset.id || '');
            });

            handle.addEventListener('dragend', function () {
                if (draggingItem) {
                    draggingItem.classList.remove('dragging');
                }

                draggingItem = null;
                refreshOrderButton();
            });
        });

        grid.addEventListener('dragover', function (event) {
            event.preventDefault();

            if (!draggingItem) {
                return;
            }

            const closest = getClosestItem(grid, event.clientX, event.clientY);

            if (!closest) {
                grid.appendChild(draggingItem);
                return;
            }

            if (closest.before) {
                grid.insertBefore(draggingItem, closest.element);
            } else {
                grid.insertBefore(draggingItem, closest.element.nextSibling);
            }
        });
    }

    if (saveOrderButton) {
        saveOrderButton.addEventListener('click', function () {
            orderValue.value = JSON.stringify(collectOrder());
            showModal(orderModal);
        });
    }
</script>
</body>
</html>
