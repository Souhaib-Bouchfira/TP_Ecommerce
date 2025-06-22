<?php

session_start();
error_reporting(E_ALL & ~E_NOTICE);

define('USERS_FILE', 'users.json');
define('CARTS_DIR', 'carts');


if (!is_dir(CARTS_DIR)) {
    mkdir(CARTS_DIR, 0775, true);
}


$productsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$skip = ($currentPage - 1) * $productsPerPage;


function readJsonFile($filePath) {
    if (!file_exists($filePath)) return [];
    return json_decode(file_get_contents($filePath), true) ?: [];
}

function writeJsonFile($filePath, $data) {
    return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

function getCartFilePath($username) {

    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    return CARTS_DIR . '/' . $safeUsername . '.json';
}

function fetchProducts($limit, $skip) {
    $apiUrl = "https://dummyjson.com/products?limit={$limit}&skip={$skip}";
    $json_data = @file_get_contents($apiUrl);
    if ($json_data === false) return null;
    return json_decode($json_data, true);
}

$errors = [];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$postData = $isAjax ? json_decode(file_get_contents('php://input'), true) : $_POST;
$action = $postData['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if ($action === 'update_cart' && isset($_SESSION['username'])) {
        $cartData = $postData['cart'] ?? [];
        $cartFile = getCartFilePath($_SESSION['username']);
        if (writeJsonFile($cartFile, $cartData)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
        } else {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save cart.']);
        }
        exit;
    }

    if ($action === 'signup') {
        $username = trim($postData['username']);
        $password = $postData['password'];
        $users = readJsonFile(USERS_FILE);

        if (empty($username) || empty($password)) {
            $errors[] = "Le nom d'utilisateur et le mot de passe sont requis.";
        } elseif (isset($users[$username])) {
            $errors[] = "Ce nom d'utilisateur existe déjà.";
        } else {
            $users[$username] = password_hash($password, PASSWORD_DEFAULT);
            if (writeJsonFile(USERS_FILE, $users)) {

                writeJsonFile(getCartFilePath($username), []);
                $_SESSION['username'] = $username;
                header('Location: index.php?page=home&signup=success');
                exit;
            } else {
                $errors[] = "Erreur lors de la création du compte.";
            }
        }
    }

    if ($action === 'login') {
        $username = trim($postData['username']);
        $password = $postData['password'];
        $users = readJsonFile(USERS_FILE);

        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            $_SESSION['username'] = $username;

            header('Location: index.php?page=home&login=success');
            exit;
        } else {
            $errors[] = "Nom d'utilisateur ou mot de passe incorrect.";
        }
    }


    if ($action === 'logout') {
        session_destroy();
        header('Location: index.php?page=home');
        exit;
    }
}


$page = $_GET['page'] ?? 'home';
$productData = null;
if ($page === 'home') {
    $productData = fetchProducts($productsPerPage, $skip);
}

$isLoggedIn = isset($_SESSION['username']);
$userCart = [];
if ($isLoggedIn) {
    $cartFile = getCartFilePath($_SESSION['username']);
    $userCart = readJsonFile($cartFile);
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOUCHFIRA_Souhaib</title>

    <link rel="stylesheet" href="https://bootswatch.com/5/vapor/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        .product-card { transition: transform .2s ease-in-out, box-shadow .2s ease-in-out; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(255, 0, 255, 0.25); }
        .product-card img { height: 220px; object-fit: contain; background-color: rgba(255,255,255,0.05); }
        .filter-bar { background-color: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.25rem; margin-bottom: 2rem; }
    </style>
</head>





<body>
    <nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark">
        <div class="container">
            <a class="navbar-brand" href="index.php?page=home"><i class="bi bi-box-seam-fill"></i> BOUCHFIRA SHOP</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link <?php if ($page === 'home') echo 'active'; ?>" href="index.php?page=home">Accueil</a></li>
                    <?php if ($isLoggedIn): ?><li class="nav-item"><a class="nav-link <?php if ($page === 'profile') echo 'active'; ?>" href="index.php?page=profile">Profil</a></li><?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php if ($page === 'cart') echo 'active'; ?>" href="index.php?page=cart">
                            <i class="bi bi-cart"></i> Panier <span class="badge rounded-pill bg-info" id="cart-count">0</span>
                        </a>
                    </li>
                    <li class="nav-item"><button class="btn nav-link" id="theme-toggler"><i class="bi bi-sun-fill"></i></button></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <form method="POST" action="index.php"><input type="hidden" name="action" value="logout"><button type="submit" class="dropdown-item">Se déconnecter</button></form>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=login"><i class="bi bi-box-arrow-in-right"></i> Se connecter</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=signup">S'inscrire</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    

    <main class="container mt-4 mb-5">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php 
        switch($page):
            case 'home':
        ?>
            <div class="alert alert-dismissible alert-success">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <h4 class="alert-heading">Bienvenue sur BOUCHFIRA SHOP</h4>
                <p>Veuillez vous connecter afin de sauvegarder votre panier en temps réel.</p>
            </div>

            <?php if ($productData): ?>
                <div class="filter-bar">
                     <div class="row g-3 align-items-end">
                        <div class="col-md-5"><label for="search-input" class="form-label">Recherche</label><input type="text" id="search-input" class="form-control" placeholder="Rechercher des produits..."></div>
                        <div class="col-md-4"><label for="sort-by" class="form-label">Trier par</label><select class="form-select" id="sort-by"><option value="title-asc">Titre (A-Z)</option><option value="title-desc">Titre (Z-A)</option><option value="price-asc">Prix (Croissant)</option><option value="price-desc">Prix (Décroissant)</option></select></div>
                         <div class="col-md-3"><form method="GET"><input type="hidden" name="page" value="home"><label for="limit" class="form-label">Produits par page</label><select name="limit" id="limit" class="form-select" onchange="this.form.submit()"><option value="12" <?php if ($productsPerPage == 12) echo 'selected'; ?>>12</option><option value="24" <?php if ($productsPerPage == 24) echo 'selected'; ?>>24</option><option value="36" <?php if ($productsPerPage == 36) echo 'selected'; ?>>36</option></select></form></div>
                    </div>
                </div>
                
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4" id="product-grid">
                    <?php foreach ($productData['products'] as $product): ?>
                    <div class="col product-item" data-title="<?php echo htmlspecialchars(strtolower($product['title'])); ?>" data-price="<?php echo htmlspecialchars($product['price']); ?>">
                        <div class="card h-100 product-card">
                            <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['title']); ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['title']); ?></h5>
                                <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars(substr($product['description'], 0, 70)); ?>...</p>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <span class="fw-bold fs-5 text-info"><?php echo number_format($product['price'], 2, ',', ' '); ?>€</span>
                                    <button class="btn btn-primary add-to-cart-btn" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['title']); ?>" data-price="<?php echo $product['price']; ?>" data-img="<?php echo htmlspecialchars($product['thumbnail']); ?>"><i class="bi bi-cart-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <nav aria-label="Page navigation" class="mt-5"><ul class="pagination justify-content-center"><?php $totalPages = ceil($productData['total'] / $productsPerPage); if ($currentPage > 1): ?><li class="page-item"><a class="page-link" href="?page=home&p=<?php echo $currentPage - 1; ?>&limit=<?php echo $productsPerPage; ?>">«</a></li><?php endif; for ($i = 1; $i <= $totalPages; $i++): ?><li class="page-item <?php if ($i == $currentPage) echo 'active'; ?>"><a class="page-link" href="?page=home&p=<?php echo $i; ?>&limit=<?php echo $productsPerPage; ?>"><?php echo $i; ?></a></li><?php endfor; if ($currentPage < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=home&p=<?php echo $currentPage + 1; ?>&limit=<?php echo $productsPerPage; ?>">»</a></li><?php endif; ?></ul></nav>
            <?php else: ?>
                <div class="alert alert-warning">Impossible de charger les produits. L'API est peut-être indisponible.</div>
            <?php endif; ?>
        <?php 
            break;
            case 'cart':
        ?>
            <h2><i class="bi bi-cart-check-fill"></i> Votre Panier</h2><div id="cart-container" class="mt-4"></div>
        <?php
            break;
            case 'login':
        ?>
            <div class="row justify-content-center"><div class="col-md-6"><div class="card border-primary"><div class="card-header"><h2>Se connecter</h2></div><div class="card-body"><form method="POST" action="index.php?page=login"><input type="hidden" name="action" value="login"><div class="mb-3"><label for="username" class="form-label">Nom d'utilisateur</label><input type="text" class="form-control" id="username" name="username" required></div><div class="mb-3"><label for="password" class="form-label">Mot de passe</label><input type="password" class="form-control" id="password" name="password" required></div><button type="submit" class="btn btn-primary w-100">Connexion</button></form></div><div class="card-footer text-center">Pas de compte? <a href="index.php?page=signup">Inscrivez-vous ici</a>.</div></div></div></div>
        <?php 
            break;
            case 'signup':
        ?>
             <div class="row justify-content-center"><div class="col-md-6"><div class="card border-info"><div class="card-header"><h2>Créer un compte</h2></div><div class="card-body"><form method="POST" action="index.php?page=signup"><input type="hidden" name="action" value="signup"><div class="mb-3"><label for="username" class="form-label">Nom d'utilisateur</label><input type="text" class="form-control" id="username" name="username" required></div><div class="mb-3"><label for="password" class="form-label">Mot de passe</label><input type="password" class="form-control" id="password" name="password" required></div><button type="submit" class="btn btn-info w-100">S'inscrire</button></form></div><div class="card-footer text-center">Déjà un compte? <a href="index.php?page=login">Connectez-vous</a>.</div></div></div></div>
        <?php 
            break;
            case 'profile':
                if (!$isLoggedIn) { header('Location: index.php?page=login'); exit; }
        ?>
            <h2>Profil de <?php echo htmlspecialchars($_SESSION['username']); ?></h2><p>Cette page est un espace réservé pour les informations du profil utilisateur.</p>
        <?php 
            break;
            default: echo "<h2>Page non trouvée</h2><p>La page que vous cherchez n'existe pas.</p>"; break;
        endswitch;
        ?>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = '<?php echo $page; ?>';
        const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
        const serverCartData = <?php echo json_encode($userCart); ?>;
        

        const themeToggler = document.getElementById('theme-toggler');
        const htmlEl = document.documentElement;
        let currentTheme = localStorage.getItem('theme') || 'dark';
        htmlEl.setAttribute('data-bs-theme', currentTheme);
        themeToggler.querySelector('i').className = currentTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        themeToggler.addEventListener('click', () => {
            currentTheme = htmlEl.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            htmlEl.setAttribute('data-bs-theme', currentTheme);
            themeToggler.querySelector('i').className = currentTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            localStorage.setItem('theme', currentTheme);
        });


        const Cart = {
            items: [],
            
            init() {
                this.items = isLoggedIn ? serverCartData : JSON.parse(localStorage.getItem('cart')) || [];
                this.updateCount();
            },

            add(product) {
                const existingItem = this.items.find(item => item.id == product.id);
                if (existingItem) { existingItem.quantity++; } 
                else { this.items.push({ ...product, quantity: 1 }); }
                this.save();
                this.updateCount();
            },

            update(productId, quantity) {
                const item = this.items.find(i => i.id == productId);
                if (!item) return;

                if (quantity > 0) { item.quantity = quantity; }
                else { this.items = this.items.filter(i => i.id != productId); }
                
                this.save();
                this.updateCount();
                if (currentPage === 'cart') this.render();
            },

            remove(productId) {
                this.items = this.items.filter(item => item.id != productId);
                this.save();
                this.updateCount();
                if (currentPage === 'cart') this.render();
            },

            clear() {
                this.items = [];
                this.save();
                this.updateCount();
                if (currentPage === 'cart') this.render();
            },
            
            async save() {
                if (isLoggedIn) {
                    try {
                        await fetch('index.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({ action: 'update_cart', cart: this.items })
                        });
                    } catch (error) { console.error('Failed to save cart to server:', error); }
                } else {
                    localStorage.setItem('cart', JSON.stringify(this.items));
                }
            },

            updateCount() {
                const totalItems = this.items.reduce((sum, item) => sum + item.quantity, 0);
                document.getElementById('cart-count').textContent = totalItems;
            },

            render() {
                const container = document.getElementById('cart-container');
                if (!container) return;

                if (this.items.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">Votre panier est vide. <a href="index.php?page=home" class="alert-link">Commencez vos achats!</a></div>';
                    return;
                }

                let subtotal = 0;
                const itemsHtml = this.items.map(item => {
                    const itemTotal = item.price * item.quantity;
                    subtotal += itemTotal;
                    return `<tr><td><div class="d-flex align-items-center"><img src="${item.img}" alt="${item.name}" width="60" class="me-3 rounded"><span>${item.name}</span></div></td><td class="text-center">${item.price.toFixed(2)}€</td><td><input type="number" value="${item.quantity}" min="1" class="form-control form-control-sm text-center cart-quantity-input" style="width: 75px; margin: auto;" data-id="${item.id}"></td><td class="text-end">${itemTotal.toFixed(2)}€</td><td class="text-center"><button class="btn btn-outline-danger btn-sm remove-from-cart-btn" data-id="${item.id}">×</button></td></tr>`;
                }).join('');

                container.innerHTML = `<div class="card border-secondary"><div class="card-body"><table class="table table-hover align-middle"><thead><tr><th style="width: 50%;">Produit</th><th class="text-center">Prix</th><th class="text-center">Quantité</th><th class="text-end">Total</th><th></th></tr></thead><tbody>${itemsHtml}</tbody></table></div><div class="card-footer d-flex justify-content-between align-items-center"><div><button class="btn btn-danger" id="clear-cart-btn">Vider le panier</button></div><div class="text-end"><h4 class="mb-0">Total: <span class="text-info">${subtotal.toFixed(2)}€</span></h4><button class="btn btn-success mt-2">Passer la commande</button></div></div></div>`;
                
                container.querySelector('#clear-cart-btn').addEventListener('click', () => this.clear());
                container.querySelectorAll('.cart-quantity-input').forEach(input => {
                    input.addEventListener('change', e => this.update(e.target.dataset.id, parseInt(e.target.value, 10)));
                });
                container.querySelectorAll('.remove-from-cart-btn').forEach(button => {
                    button.addEventListener('click', e => this.remove(e.currentTarget.dataset.id));
                });
            }
        };

        Cart.init();
        

        if (currentPage === 'home') {
            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    const btn = e.currentTarget;
                    Cart.add({ id: btn.dataset.id, name: btn.dataset.name, price: parseFloat(btn.dataset.price), img: btn.dataset.img });
                    btn.innerHTML = '<i class="bi bi-check-lg"></i>'; btn.classList.add('btn-success'); btn.classList.remove('btn-primary');
                    setTimeout(() => { btn.innerHTML = '<i class="bi bi-cart-plus"></i>'; btn.classList.remove('btn-success'); btn.classList.add('btn-primary'); }, 1200);
                });
            });

            const productGrid = document.getElementById('product-grid');
            const searchInput = document.getElementById('search-input');
            const sortBySelect = document.getElementById('sort-by');
            function filterAndSortProducts() {
                if (!productGrid) return;
                const items = Array.from(productGrid.querySelectorAll('.product-item'));
                const searchTerm = searchInput.value.toLowerCase().trim();
                items.forEach(item => { item.style.display = item.dataset.title.includes(searchTerm) ? '' : 'none'; });
                let visibleItems = items.filter(item => item.style.display !== 'none');
                visibleItems.sort((a, b) => {
                    switch (sortBySelect.value) {
                        case 'title-asc': return a.dataset.title.localeCompare(b.dataset.title);
                        case 'title-desc': return b.dataset.title.localeCompare(a.dataset.title);
                        case 'price-asc': return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                        case 'price-desc': return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    }
                });
                visibleItems.forEach(item => productGrid.appendChild(item));
            }
            if(searchInput) searchInput.addEventListener('input', filterAndSortProducts);
            if(sortBySelect) sortBySelect.addEventListener('change', filterAndSortProducts);
        }

        if (currentPage === 'cart') {
            Cart.render();
        }
    });
    </script>
</body>
</html>