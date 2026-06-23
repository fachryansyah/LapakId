<?php

declare(strict_types=1);

use Fahri\LapakId\Controllers\Admin\DashboardController;
use Fahri\LapakId\Controllers\Admin\CategoryController;
use Fahri\LapakId\Controllers\Admin\ProductController;
use Fahri\LapakId\Controllers\Admin\ProductItemController;
use Fahri\LapakId\Controllers\Admin\TransactionController;
use Fahri\LapakId\Controllers\Admin\UploadController;
use Fahri\LapakId\Controllers\AuthController;
use Fahri\LapakId\Controllers\CheckoutController;
use Fahri\LapakId\Controllers\HomeController;
use Fahri\LapakId\Controllers\WebhookController;
use Fahri\LapakId\Controllers\ProfileController;
use Phroute\Phroute\RouteCollector;

$router = new RouteCollector();

$router->get('/', [HomeController::class, 'index']);
$router->get('/products', [HomeController::class, 'products']);
$router->get('/categories/{id:i}', [HomeController::class, 'categoryDetail']);
$router->get('/product/{id:i}', [HomeController::class, 'product']);
$router->post('/checkout', [CheckoutController::class, 'store']);
$router->get('/invoice/{invoiceCode}', [CheckoutController::class, 'invoice']);
$router->get('/invoice/{invoiceCode}/status', [CheckoutController::class, 'invoiceStatus']);
$router->post('/payment/hook', [WebhookController::class, 'paymentHook']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/profile', [ProfileController::class, 'index']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->get('/transaction', [\Fahri\LapakId\Controllers\TransactionController::class, 'index']);

$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/categories', [CategoryController::class, 'index']);
$router->get('/admin/categories/create', [CategoryController::class, 'create']);
$router->post('/admin/categories', [CategoryController::class, 'store']);
$router->post('/admin/upload/category/{field}', [UploadController::class, 'categoryMedia']);
$router->get('/admin/categories/{id:i}/edit', [CategoryController::class, 'edit']);
$router->post('/admin/categories/{id:i}/update', [CategoryController::class, 'update']);
$router->post('/admin/categories/{id:i}/delete', [CategoryController::class, 'delete']);

$router->get('/admin/products', [ProductController::class, 'index']);
$router->get('/admin/products/create', [ProductController::class, 'create']);
$router->post('/admin/products', [ProductController::class, 'store']);
$router->get('/admin/products/{id:i}/edit', [ProductController::class, 'edit']);
$router->post('/admin/products/{id:i}/update', [ProductController::class, 'update']);
$router->post('/admin/products/{id:i}/delete', [ProductController::class, 'delete']);

$router->get('/admin/products/{productId:i}/items', [ProductItemController::class, 'index']);
$router->get('/admin/products/{productId:i}/items/create', [ProductItemController::class, 'create']);
$router->post('/admin/products/{productId:i}/items', [ProductItemController::class, 'store']);
$router->get('/admin/products/{productId:i}/items/{id:i}/edit', [ProductItemController::class, 'edit']);
$router->post('/admin/products/{productId:i}/items/{id:i}/update', [ProductItemController::class, 'update']);
$router->post('/admin/products/{productId:i}/items/{id:i}/delete', [ProductItemController::class, 'delete']);

$router->post('/admin/upload/product-item', [UploadController::class, 'productItemImage']);

$router->get('/admin/transactions', [TransactionController::class, 'index']);

return $router;
