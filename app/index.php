<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Image Upload</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        form { margin: 20px 0; }
        .success { color: green; }
        .error { color: red; }
        img { max-width: 300px; margin-top: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<h2>PHP Image Upload</h2>

<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="image" accept="image/*" required>
    <br><br>
    <input type="submit" name="upload" value="Upload Image">
</form>

<?php
// Configuration
$uploadDir = 'uploads/';
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (isset($_POST['upload'])) {
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validation
        if (!in_array($fileType, $allowedTypes)) {
            echo '<p class="error">Error: Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>';
        } 
        elseif ($fileSize > $maxSize) {
            echo '<p class="error">Error: File size must be less than 5MB.</p>';
        } 
        else {
            // Generate unique filename to prevent overwriting
            $newFileName = uniqid('img_') . '.' . $fileType;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                echo '<p class="success">✅ Image uploaded successfully!</p>';
                echo '<img src="' . $destPath . '" alt="Uploaded Image">';
            } else {
                echo '<p class="error">Error: Failed to move uploaded file.</p>';
            }
        }
    } else {
        echo '<p class="error">Error: ' . $_FILES['image']['error'] . '</p>';
    }
}
?>

</body>
</html>
