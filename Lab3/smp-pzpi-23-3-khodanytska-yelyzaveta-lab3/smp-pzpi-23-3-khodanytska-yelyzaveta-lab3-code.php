<?php
session_start();

$db = new SQLite3('products.db');

$products = [];
$result = $db->query('SELECT * FROM products');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $products[$row['id']] = [
        'name' => $row['name'],
        'price' => $row['price'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'add' && isset($_POST['product_id'])) {
        $id = (int)$_POST['product_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($quantity <= 0 || $quantity > 100) {
            $_SESSION['form_error'] = "Перевірте, будь ласка, введену кількість товару.";
            $_SESSION['form_data'] = $_POST;
            header("Location: lab3.php?page=products");
            exit;
        }

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = min($_SESSION['cart'][$id] + $quantity, 100);
        } else {
            $_SESSION['cart'][$id] = $quantity;
        }

        $_SESSION['message'] = "Товар додано до кошика!";
        header("Location: lab3.php?page=products");
        exit;
    }

    if ($action === 'remove' && isset($_POST['product_id'])) {
        $id = (int)$_POST['product_id'];
        unset($_SESSION['cart'][$id]);
        $_SESSION['message'] = "Товар видалено з кошика!";
        header("Location: lab3.php?page=cart");
        exit;
    }

    if ($action === 'checkout') {
        $_SESSION['cart'] = [];
        $_SESSION['message'] = "Дякуємо за покупку!";
        header("Location: lab3.php?page=cart");
        exit;
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'products';
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
        }
        html, body {
            height: 100vh;
            margin: 0;
            font-family: system-ui, sans-serif;
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
            min-height: calc(100vh - 180px);
            padding-bottom: 60px;
            box-sizing: border-box;
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
        <a href="lab3.php?page=home">Головна</a> | 
        <a href="lab3.php?page=products">Товари</a> | 
        <a href="lab3.php?page=cart">Кошик</a>
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
    <h2>Ласкаво просимо!</h2>
    <p>Раді знову вас бачити на нашому сайті.</p>

<?php elseif ($page === 'products'): ?>
    <h2>Товари</h2>
    <?php foreach ($products as $id => $product): ?>
        <div class="product">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>Ціна: <?php echo $product['price']; ?> грн</p>
            <form method="post" action="lab3.php?page=products">
                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                <input type="hidden" name="action" value="add">
                Кількість:
                <input type="number" name="quantity"
                       value="<?php echo isset($form_data['product_id']) && $form_data['product_id'] == $id ? (int)$form_data['quantity'] : 1; ?>"
                       min="1" max="100">
                <button type="submit">Купити</button>
            </form>
        </div>
    <?php endforeach; ?>

<?php elseif ($page === 'cart'): ?>
    <h2>Кошик</h2>
    <?php if (empty($_SESSION['cart'])): ?>
        <p>Ваш кошик порожній. <a href="lab3.php?page=products" class="go-back">Перейти до покупок</a></p>
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
                <form method="post" action="lab3.php?page=cart">
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
<?php endif; ?>

</main>

<footer>
    <nav>
        <a href="lab3.php?page=home">Головна</a> | 
        <a href="lab3.php?page=products">Товари</a> | 
        <a href="lab3.php?page=cart">Кошик</a>
    </nav>
</footer>

</body>
</html>
