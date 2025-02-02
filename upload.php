<?php
require_once __DIR__ . '/config.php';  // <--- Подключаем конфиг

$secretKey = '12345678901234567890123456789012';

function generateUUID() {
    return bin2hex(random_bytes(6));
}

/** Шифруем строку (имя файла) */
function encryptValue($plaintext, $secretKey)
{
    $cipher = "aes-256-cbc";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt($plaintext, $cipher, $secretKey, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

/** Расшифровка (если понадобится) */
function decryptValue($encoded, $secretKey)
{
    $cipher = "aes-256-cbc";
    $data = base64_decode($encoded);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $cipher, $secretKey, 0, $iv);
}

// Инициализируем JSON
if (!file_exists('images.json')) {
    file_put_contents('images.json', json_encode([]));
}
$data = json_decode(file_get_contents('images.json'), true);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $files = $_FILES['photos'];
    $uploadedInfo = [];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $originalName = $files['name'][$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // Проверка расширения
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                continue;
            }

            // Определяем новое имя файла (1.jpg, 2.jpg и т.д.)
            $newId = 1;
            $filesInI = scandir(__DIR__ . '/i');
            foreach ($filesInI as $f) {
                if (preg_match('/^(\d+)\./', $f, $m)) {
                    $num = (int)$m[1];
                    if ($num >= $newId) {
                        $newId = $num + 1;
                    }
                }
            }
            $newFilename = $newId . '.' . $ext;
            move_uploaded_file($tmpName, __DIR__ . '/i/' . $newFilename);

            // Генерируем UUID
            $uuid = generateUUID();
            while (isset($data[$uuid])) {
                $uuid = generateUUID();
            }

            // Шифруем имя файла
            $encryptedFile = encryptValue($newFilename, $secretKey);

            // Сохраняем
            $data[$uuid] = [
                'file' => $encryptedFile,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $uploadedInfo[] = [
                'uuid' => $uuid,
                'file' => $newFilename,
                'created_at' => $data[$uuid]['created_at']
            ];
        }
    }
    
    // Запись обратно
    file_put_contents('images.json', json_encode($data));

    // Вывод результат
    echo '<!DOCTYPE html><html><head>
          <meta charset="utf-8">
          <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.2.7/dist/tailwind.min.css" rel="stylesheet">
          <title>Загружено</title>
          </head><body class="bg-gray-50 min-h-screen p-6">';
    echo '<div class="max-w-xl mx-auto bg-white p-6 rounded shadow">';
    echo '<h1 class="text-2xl font-bold mb-4">Загружено</h1>';
    if (empty($uploadedInfo)) {
        echo '<p class="text-red-500">Ни одного файла не было загружено или все были некорректного формата.</p>';
    }

    // Формируем ссылки:
    foreach ($uploadedInfo as $info) {
        $uuid = $info['uuid'];
        $absLink = $BASE_URL . '/' . $uuid;
        echo '<div class="mb-6 p-4 border rounded bg-gray-50">';
        echo '<p><span class="font-semibold">Файл:</span> ' . htmlspecialchars($info['file']) . '</p>';
        echo '<p><span class="font-semibold">Дата загрузки:</span> ' . htmlspecialchars($info['created_at']) . '</p>';
        echo '<p><span class="font-semibold">Ссылка:</span> 
                <a class="text-blue-600 underline break-all" href="' . $absLink . '">' . $absLink . '</a>
              </p>';
        echo '<img src="' . $BASE_URL . '/i/' . $info['file'] . '" alt="" class="mt-2 max-h-48 border rounded shadow-sm">';
        echo '</div>';
    }
    echo '<div class="mt-4 space-x-2">';
    echo '<a href="' . $BASE_URL . '/upload" class="px-4 py-2 bg-blue-500 text-white rounded">Загрузить ещё</a>';
    echo '<a href="' . $BASE_URL . '/report" class="px-4 py-2 bg-gray-200 rounded">Посмотреть отчёт</a>';
    echo '</div></div></body></html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка изображений</title>
    <link rel="icon" type="image/png" href="./assets/favicon.ico">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-gray-100 p-8 space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Загрузка файлов</h1>
            <p class="text-gray-500">Выберите изображения для загрузки</p>
        </div>

        <form action="" method="post" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Выберите файлы</label>
                <div
                    class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition">
                    <input type="file" name="photos[]" multiple
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="updateFileList(this)">
                    <div class="space-y-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm text-gray-600">Перетащите файлы или <span
                                class="text-blue-600 font-semibold">выберите</span></p>
                        <p id="fileList" class="text-xs text-gray-500"></p>
                    </div>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition duration-300 ease-in-out transform hover:scale-101 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                Загрузить изображения
            </button>
        </form>

    </div>

    <script>
    function updateFileList(input) {
        const fileList = document.getElementById('fileList');
        if (input.files.length > 0) {
            const names = Array.from(input.files).map(f => f.name).join(', ');
            fileList.textContent = `Выбрано файлов: ${input.files.length} (${names})`;
        } else {
            fileList.textContent = '';
        }
    }
    </script>
</body>

</html>