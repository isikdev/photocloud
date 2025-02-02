<?php
session_start();

require_once __DIR__ . '/config.php';  // <--- Подключаем конфиг

$reportPassword = '123';  // пароль для просмотра
$secretKey = '12345678901234567890123456789012';

// Функция расшифровки
function decryptValue($encoded, $secretKey)
{
    $cipher = "aes-256-cbc";
    $data = base64_decode($encoded);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $cipher, $secretKey, 0, $iv);
}

// Подгружаем images.json
if (!file_exists('images.json')) {
    file_put_contents('images.json', json_encode([]));
}
$data = json_decode(file_get_contents('images.json'), true);

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_images'])) {
    $imagesToDelete = $_POST['delete_images'] ?? [];
    foreach ($imagesToDelete as $uuid) {
        if (isset($data[$uuid])) {
            $encryptedFilename = $data[$uuid]['file'];
            $filename = decryptValue($encryptedFilename, $secretKey);
            $filepath = __DIR__ . '/i/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            unset($data[$uuid]);
        }
    }
    file_put_contents('images.json', json_encode($data));
    // Перенаправляем обратно
    header('Location: ' . $BASE_URL . '/report');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Изображения</title>
    <link rel="icon" type="image/png" href="./assets/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Простой пароль
        var pass = prompt("Введите пароль для просмотра отчёта:");
        if (pass !== "<?php echo $reportPassword; ?>") {
            alert("Неверный пароль!");
            window.location = "https://google.com";
        }

        // Инициализация ClipboardJS (для кнопок "копировать")
        new ClipboardJS('.copy-link');

        // Логика для выбора всех чекбоксов
        const selectAllCheckbox = document.getElementById('select-all');
        const imageCheckboxes = document.querySelectorAll('.image-checkbox');
        const deleteBtn = document.getElementById('deleteSelectedBtn');

        // По умолчанию кнопка удаления неактивна
        deleteBtn.disabled = true;
        deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');

        // Функция обновления состояния кнопки удаления
        function updateDeleteButtonState() {
            let anyChecked = false;
            imageCheckboxes.forEach(checkbox => {
                if (checkbox.checked) anyChecked = true;
            });
            if (anyChecked) {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                deleteBtn.disabled = true;
                deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // Выбрать все
        selectAllCheckbox.addEventListener('change', function() {
            imageCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateDeleteButtonState();
        });

        // По отдельности
        imageCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateDeleteButtonState();
            });
        });
    });

    // Показываем уведомление «Скопировано»
    function showCopiedNotification(el) {
        const originalText = el.innerHTML;
        el.innerHTML = 'Скопировано!';
        setTimeout(() => {
            el.innerHTML = originalText;
        }, 1500);
    }
    </script>
</head>

<body class="bg-gray-50 text-gray-800">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="bg-white shadow-md rounded-xl overflow-hidden">
            <div class="bg-gray-100 p-6 border-b border-gray-200">
                <h1 class="text-2xl font-light text-gray-700">Загруженные</h1>
            </div>

            <?php
            // Собираем список
            $items = [];
            foreach ($data as $uuid => $info) {
                $items[] = [
                    'uuid' => $uuid,
                    'file' => $info['file'],
                    'created_at' => $info['created_at']
                ];
            }

            // Сортируем по дате (свежие сверху)
            usort($items, function($a, $b) {
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            });
            ?>

            <?php if (empty($items)): ?>
            <!-- Нет загруженных изображений -->
            <div class="p-12 text-center">
                <p class="text-gray-500 mb-4">Нет загруженных изображений</p>
                <a href="<?php echo $BASE_URL; ?>/upload"
                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Загрузить изображения
                </a>
            </div>
            <?php else: ?>
            <form method="POST" action="">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="form-checkbox text-gray-600 rounded">
                                </th>
                                <th class="px-4 py-3 text-left">Дата</th>
                                <th class="px-4 py-3 text-left">Изображение</th>
                                <th class="px-4 py-3 text-left">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item):
                                    $uuid = $item['uuid'];
                                    $encryptedFilename = $item['file'];
                                    $filename = decryptValue($encryptedFilename, $secretKey);
                                    $created = $item['created_at'];

                                    // Путь к файлу для превью (миниатюры):
                                    $linkPreview = $BASE_URL . '/i/' . $filename;

                                    // Абсолютная ссылка (для копирования и просмотра)
                                    $absoluteLink = $BASE_URL . '/' . $uuid;
                                ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="delete_images[]"
                                        value="<?php echo htmlspecialchars($uuid); ?>"
                                        class="form-checkbox text-gray-600 rounded image-checkbox">
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php echo htmlspecialchars($created); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <img src="<?php echo $linkPreview; ?>" alt="Изображение"
                                        class="w-32 h-32 object-cover rounded-md shadow-sm">
                                </td>
                                <td class="px-4 py-3 space-y-2">
                                    <!-- Кнопка копирования (абсолютная ссылка) -->
                                    <button type="button"
                                        class="copy-link w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                                        data-clipboard-text="<?php echo $absoluteLink; ?>"
                                        onclick="showCopiedNotification(this)">
                                        Копировать
                                    </button>
                                    <!-- Кнопка просмотра -->
                                    <a href="<?php echo $absoluteLink; ?>" target="_blank"
                                        class="block w-full text-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                        Просмотр
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Подвал таблицы -->
                <div class="p-4 bg-gray-50 border-t flex items-center justify-between">
                    <!-- Кнопка Удалить выбранные -->
                    <button id="deleteSelectedBtn" type="submit"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                        onclick="return confirm('Вы уверены, что хотите удалить выбранные изображения?');">
                        Удалить выбранные
                    </button>
                    <!-- Общее число -->
                    <div class="text-gray-600">
                        Всего изображений: <span class="font-semibold"><?php echo count($items); ?></span>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>