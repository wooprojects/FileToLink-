<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// تنظیمات
$upload_dir = 'uploads/';
$max_file_size = 20 * 1024 * 1024; // 20MB
$allowed_types = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'video' => ['mp4', 'avi', 'mov', 'mkv'],
    'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
    'document' => ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar']
];

// ایجاد پوشه آپلود اگر وجود ندارد
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// فایل دیتابیس ساده (می‌توانید با MySQL جایگزین کنید)
$db_file = 'files.json';

function getFilesDatabase() {
    global $db_file;
    if (!file_exists($db_file)) {
        return [];
    }
    return json_decode(file_get_contents($db_file), true) ?: [];
}

function saveFilesDatabase($data) {
    global $db_file;
    file_put_contents($db_file, json_encode($data, JSON_PRETTY_PRINT));
}

function getFileType($extension) {
    global $allowed_types;
    foreach ($allowed_types as $type => $extensions) {
        if (in_array(strtolower($extension), $extensions)) {
            return $type;
        }
    }
    return 'document';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $k = 1024;
    $sizes = ["B", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return number_format(($bytes / pow($k, $i)), 2) . " " . $sizes[$i];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            handleUpload();
            break;
            
        case 'getFiles':
            getFiles();
            break;
            
        case 'delete':
            deleteFile();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleUpload() {
    global $upload_dir, $max_file_size, $allowed_types;
    
    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }
    
    $uploaded_files = $_FILES['files'];
    $links = [];
    
    // بررسی اینکه آیا آرایه است یا نه
    $files = is_array($uploaded_files['name']) ? 
        reorganizeFiles($uploaded_files) : 
        [$uploaded_files];
    
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // بررسی حجم فایل
        if ($file['size'] > $max_file_size) {
            throw new Exception('File size too large: ' . $file['name']);
        }
        
        // بررسی نوع فایل
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_type = getFileType($file_extension);
        
        // تولید نام یکتا برای فایل
        $file_id = uniqid() . '_' . bin2hex(random_bytes(8));
        $file_name = $file_id . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // آپلود فایل
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save file: ' . $file['name']);
        }
        
        // ذخیره اطلاعات در دیتابیس
        $files_db = getFilesDatabase();
        $file_data = [
            'id' => $file_id,
            'original_name' => $file['name'],
            'saved_name' => $file_name,
            'type' => $file_type,
            'size' => $file['size'],
            'upload_date' => time(),
            'url' => getFileUrl($file_name)
        ];
        
        $files_db[] = $file_data;
        saveFilesDatabase($files_db);
        
        $links[] = [
            'name' => $file['name'],
            'url' => $file_data['url'],
            'type' => $file_type
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Files uploaded successfully',
        'links' => $links
    ]);
}
function getFiles() {
    $files_db = getFilesDatabase();
    $files = [];
    $total_size = 0;
    
    foreach ($files_db as $file_data) {
        $files[] = [
            'id' => $file_data['id'],
            'name' => $file_data['original_name'],
            'type' => $file_data['type'],
            'size' => $file_data['size'],
            'url' => $file_data['url'],
            'upload_date' => $file_data['upload_date']
        ];
        $total_size += $file_data['size'];
    }
    
    // مرتب کردن بر اساس تاریخ (جدیدترین اول)
    usort($files, function($a, $b) {
        return $b['upload_date'] - $a['upload_date'];
    });
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'stats' => [
            'totalFiles' => count($files),
            'totalSize' => formatFileSize($total_size)
        ]
    ]);
}

function deleteFile() {
    global $upload_dir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $file_id = $input['fileId'] ?? '';
    
    if (!$file_id) {
        throw new Exception('File ID not provided');
    }
    
    $files_db = getFilesDatabase();
    $file_found = false;
    
    foreach ($files_db as $key => $file_data) {
        if ($file_data['id'] === $file_id) {
            // حذف فایل از سرور
            $file_path = $upload_dir . $file_data['saved_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // حذف از دیتابیس
            unset($files_db[$key]);
            $file_found = true;
            break;
        }
    }
    
    if ($file_found) {
        saveFilesDatabase(array_values($files_db));
        echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
    } else {
        throw new Exception('File not found');
    }
}

function reorganizeFiles($files) {
    $reorganized = [];
    $file_count = count($files['name']);
    $file_keys = array_keys($files);
    
    for ($i = 0; $i < $file_count; $i++) {
        foreach ($file_keys as $key) {
            $reorganized[$i][$key] = $files[$key][$i];
        }
    }
    
    return $reorganized;
}

function getFileUrl($file_name) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    return rtrim($base_url, '/') . '/uploads/' . $file_name;
}
?>
