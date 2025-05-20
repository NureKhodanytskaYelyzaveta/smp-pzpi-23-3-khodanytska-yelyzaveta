<?php
session_start();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: lab4.php?page=home");
    exit;
}

$users = [
    'admin' => password_hash('password123', PASSWORD_DEFAULT),
    'user' => password_hash('userpass', PASSWORD_DEFAULT)
];

$db = new SQLite3('products.db');

$db->exec("CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    price INTEGER NOT NULL
)");

$products = [
    1 => ['name' => 'Молоко пастеризоване', 'price' => 12],
    2 => ['name' => 'Хліб чорний', 'price' => 9],
    3 => ['name' => 'Сир білий', 'price' => 21],
    4 => ['name' => 'Сметана 20%', 'price' => 25],
    5 => ['name' => 'Кефір 1%', 'price' => 19],
    6 => ['name' => 'Вода газована', 'price' => 18],
    7 => ['name' => 'Печиво "Весна"', 'price' => 14],
];

foreach ($products as $id => $product) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row['count'] == 0) {
        $insert = $db->prepare("INSERT INTO products (id, name, price) VALUES (:id, :name, :price)");
        $insert->bindValue(':id', $id, SQLITE3_INTEGER);
        $insert->bindValue(':name', $product['name'], SQLITE3_TEXT);
        $insert->bindValue(':price', $product['price'], SQLITE3_INTEGER);
        $insert->execute();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $message = "Усі поля обов'язкові!";
    } elseif (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['user'] = $username;
        header("Location: lab4.php?page=products");
        exit;
    } else {
        $message = "Неправильне ім'я користувача або пароль!";
    }
}

$profile_data = file_exists('profile.php') ? include 'profile.php' : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo']) && !empty($profile_data['photo']) && file_exists($profile_data['photo'])) {
    unlink($profile_data['photo']);
    $profile_data['photo'] = null;
    file_put_contents('profile.php', '<?php return ' . var_export($profile_data, true) . ';');
    $message = "Фото видалено!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && isset($_SESSION['user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    $errors = [];
    
    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($bio)) {
        $errors[] = "Усі поля обов'язкові!";
    }
    if (strlen($first_name) <= 1) {
        $errors[] = "Ім'я має бути довшим за 1 символ!";
    }
    if (strlen($last_name) <= 1) {
        $errors[] = "Прізвище має бути довшим за 1 символ!";
    }
    if (strlen($bio) < 50) {
        $errors[] = "Стисла інформація має містити щонайменше 50 символів!";
    }
    
    $birth = DateTime::createFromFormat('Y-m-d', $birth_date);
    if (!$birth || $birth > new DateTime('now -16 years')) {
        $errors[] = "Користувачу має бути щонайменше 16 років!";
    }
    
    $photo_path = $profile_data['photo'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024;
        
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Дозволені формати: JPEG, PNG, GIF!";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "Розмір файлу не має перевищувати 2 МБ!";
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $photo_path = $upload_dir . uniqid('profile_') . '_' . basename($_FILES['photo']['name']);
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $errors[] = "Не вдалося зберегти файл!";
            } else {
                if (!empty($profile_data['photo']) && file_exists($profile_data['photo'])) {
                    unlink($profile_data['photo']);
                }
            }
        }
    } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Помилка завантаження файлу!";
    }
    
    if (empty($errors)) {
        $profile_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'bio' => $bio,
            'photo' => $photo_path
        ];
        
        file_put_contents('profile.php', '<?php return ' . var_export($profile_data, true) . ';');
        $message = "Профіль успішно оновлено!";
    } else {
        $message = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['product_id']) && isset($_SESSION['user'])) {
    $id = (int)$_POST['product_id'];
    $action = $_POST['action'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'add') {
        $quantity = max(1, min(100, (int)$_POST['quantity']));
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = min($_SESSION['cart'][$id] + $quantity, 100);
        } else {
            $_SESSION['cart'][$id] = $quantity;
        }
        $message = "Товар додано до кошика!";
    }

    if ($action === 'remove' && isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        $message = "Товар видалено з кошика!";
    }

    if ($action === 'checkout') {
        $_SESSION['cart'] = [];
        $_SESSION['message'] = "Дякуємо за покупку!";
        header("Location: lab3.php?page=cart");
        exit;
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : (isset($_SESSION['user']) ? 'products' : 'home');
$message = $_SESSION['message'] ?? null;
$form_error = $_SESSION['form_error'] ?? null;
$form_data = $_SESSION['form_data'] ?? null;
unset($_SESSION['message'], $_SESSION['form_error'], $_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Продовольчий магазин "Весна"</title>
    <style>
      * { 
          box-sizing: border-box;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        html, body {
            height: 100vh;
            margin: 0;
            background: #f2f4f8;
            color: #333;
            position: relative;
        }
        header, footer {
            background-color: #005f99;
            color: white;
            height: 60px;
            padding: 15px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .wrapper {
            min-height: 100vh;
            padding-bottom: 60px;
        }
        nav a {
            color: white;
            margin: 0 15px;
            text-decoration: none;
            font-weight: bold;
        }
        main {
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            padding-top: 20px;
            padding-bottom: 80px;
        }
        h2 {
            margin-bottom: 30px;
            color: #005f99;
            border-bottom: 2px solid #005f99;
            padding-bottom: 10px;
        }
        .product {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
        }
        .product h3 { 
            margin: 0; 
            flex: 1; 
        }
        .product p { 
            margin: 0 20px; 
            color: #555; 
        }
        .product form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        input[type="number"] {
            width: 60px;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #005f99;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        button:hover {
            background-color: #004d80;
        }
        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .go-back {
            color: black;
            font-weight: bold;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        .go-back:hover {
            color: #005f99;
        }
        .login-form, .profile-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 400px;
            width: 100%;
            margin: auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        .login-form label {
            font-size: 16px;
            color: #34495e;
            font-weight: 500;
        }
        .login-form input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #dcdcdc;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .login-form input:focus, .profile-form input:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .login-form button, .profile-form button {
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            background-color: #3498db;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .login-form button:hover, .profile-form button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .login-form button:active, .profile-form button:active {
            transform: translateY(0);
        }
        .profile-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .profile-photo-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 160px;
            align-items: center;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            border: 3px solid #005f99;
            background: #f9f9f9;
            overflow: hidden;
            border-radius: 10%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .username {
            font-weight: bold;
            color: #005f99;
            margin-top: 10px;
            text-align: center;
        }
        .profile-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .profile-info .row {
            display: flex;
            gap: 15px;
        }
        .profile-info input[type="text"],
        .profile-info input[type="date"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            transition: border-color 0.2s ease-in-out;
        }
        .profile-info input[type="text"]:focus,
        .profile-info input[type="date"]:focus {
            border-color: #005f99;
            outline: none;
        }
        .profile-info textarea {
            resize: vertical;
            min-height: 120px;
            width: 100%;
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            transition: border-color 0.2s ease-in-out;
        }
        .profile-info textarea:focus {
            border-color: #005f99;
            outline: none;
        }
        .profile-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }
        .save-button-wrapper {
            margin-top: 15px;
            display: flex;
            justify-content: flex-end;
        }
        .save-button-wrapper button {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
        }
        footer {
            position: absolute;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="lab4.php?page=home">Головна</a> | 
        <?php if (isset($_SESSION['user'])): ?>
            <a href="lab4.php?page=products">Товари</a> | 
            <a href="lab4.php?page=cart">Кошик</a> | 
            <a href="lab4.php?page=profile">Профіль</a> | 
            <a href="lab4.php?action=logout">Вийти (<?php echo htmlspecialchars($_SESSION['user']); ?>)</a>
        <?php else: ?>
            <a href="lab4.php?page=login">Увійти</a>
        <?php endif; ?>
    </nav>
</header>

<main>

<?php if (!empty($message)): ?>
    <p class="message" style="color:green;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if (!empty($form_error)): ?>
    <p class="message" style="color:red;"><?php echo htmlspecialchars($form_error); ?></p>
<?php endif; ?>

<?php if ($page === 'home'): ?>
    <?php if (isset($_SESSION['user'])): ?>
        <h2>Ласкаво просимо, <?php echo htmlspecialchars($_SESSION['user']); ?>!</h2>
        <p>Раді знову вас бачити на нашому сайті.</p>
    <?php else: ?>
        <h2>Вітаємо!</h2>
        <p>Для перегляду контенту сайту, будь ласка, <a href="lab4.php?page=login" class="go-back">увійдіть</a>.</p>
    <?php endif; ?>

<?php elseif ($page === 'login' && !isset($_SESSION['user'])): ?>
    <h2>Вхід</h2>
    <form method="post" action="lab4.php?page=login" class="login-form">
        <input type="hidden" name="action" value="login">
        <label>
            Ім'я користувача:
            <input type="text" name="username" required>
        </label>
        <label>
            Пароль:
            <input type="password" name="password" required>
        </label>
        <button type="submit">Увійти</button>
    </form>

<?php elseif ($page === 'products' && isset($_SESSION['user'])): ?>
    <h2>Товари</h2>
    <?php foreach ($products as $id => $product): ?>
        <div class="product">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>Ціна: <?php echo $product['price']; ?> грн</p>
            <form method="post" action="lab4.php?page=products">
                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                <input type="hidden" name="action" value="add">
                Кількість: <input type="number" name="quantity" value="1" min="1" max="100">
                <button type="submit">Купити</button>
            </form>
        </div>
    <?php endforeach; ?>

<?php elseif ($page === 'cart' && isset($_SESSION['user'])): ?>
    <h2>Кошик</h2>
    <?php if (empty($_SESSION['cart'])): ?>
        <p>Ваш кошик порожній. <a href="lab4.php?page=products" class="go-back">Перейти до покупок</a></p>
    <?php else: ?>
        <?php
        $total = 0;
        foreach ($_SESSION['cart'] as $id => $quantity):
            if (!isset($products[$id])) continue;
            $product = $products[$id];
            $sum = $product['price'] * $quantity;
            $total += $sum;
        ?>
            <div class="product">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>Кількість: <?php echo $quantity; ?> шт. Ціна: <?php echo $sum; ?> грн</p>
                <form method="post" action="lab4.php?page=cart">
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit">Видалити</button>
                </form>
            </div>
        <?php endforeach; ?>
        <p style="text-align:right; font-weight:bold; margin-top: 20px;">Всього: <?php echo $total; ?> грн</p>
        <div style="text-align: right; margin-top: 30px;">
            <button onclick="location.href='lab3.php?page=products'" style="margin-left: 10px;">Повернутися назад</button>
            <form method="post" action="lab3.php?page=cart" style="display: inline;">
                <input type="hidden" name="action" value="checkout">
                <button type="submit">Придбати</button>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'profile' && isset($_SESSION['user'])): ?>
    <h2>Профіль</h2>
    <form method="post" action="lab4.php?page=profile" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_profile">

    <div class="profile-container" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">

        <div class="profile-photo-wrapper" style="text-align: center;">
            <div class="profile-photo">
                <img id="preview" src="<?php echo !empty($profile_data['photo']) ? htmlspecialchars($profile_data['photo']) : ''; ?>" style="width: 150px; height: 150px; object-fit: cover; border-radius: 10%;;">
            </div>

            <div class="profile-buttons" style="margin-top: 10px;">
                <label>
                    <input type="file" name="photo" accept="image/*" style="display: none;" onchange="document.getElementById('preview').src = window.URL.createObjectURL(this.files[0])">
                    <button type="button" onclick="this.previousElementSibling.click()">Завантажити</button>
                </label>

                <?php if (!empty($profile_data['photo']) && file_exists($profile_data['photo'])): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="delete_photo" value="1">
                        <button type="submit" onclick="return confirm('Видалити фото?')">Видалити фото</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-info" style="flex: 1; min-width: 300px;">
            <div class="row" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile_data['first_name'] ?? ''); ?>" placeholder="Ім'я" required style="flex: 1; min-width: 150px;">
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile_data['last_name'] ?? ''); ?>" placeholder="Прізвище" required style="flex: 1; min-width: 150px;">
                <input type="date" name="birth_date" value="<?php echo htmlspecialchars($profile_data['birth_date'] ?? ''); ?>" required style="flex: 1; min-width: 150px;">
            </div>
            <textarea name="bio" placeholder="Стисла інформація" required style="width: 100%; margin-top: 10px;"><?php echo htmlspecialchars($profile_data['bio'] ?? ''); ?></textarea>
            <div class="save-button-wrapper" style="margin-top: 10px;">
                <button type="submit">Зберегти профіль</button>
            </div>
        </div>
    </div>
</form>

<?php else: ?>
    <h2>Вітаємо!</h2>
    <p>Для перегляду контенту сайту, будь ласка, <a href="lab4.php?page=login" class="go-back">увійдіть</a>.</p>
<?php endif; ?>

</main>

<footer>
    <nav>
        <a href="lab4.php?page=home">Головна</a> | 
        <?php if (isset($_SESSION['user'])): ?>
            <a href="lab4.php?page=products">Товари</a> | 
            <a href="lab4.php?page=cart">Кошик</a> | 
            <a href="lab4.php?page=profile">Профіль</a> | 
            <a href="lab4.php?action=logout">Вийти</a>
        <?php else: ?>
            <a href="lab4.php?page=login">Увійти</a>
        <?php endif; ?>
    </nav>
</footer>

</body>
</html>
