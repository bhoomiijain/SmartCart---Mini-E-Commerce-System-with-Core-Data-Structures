<?php
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | SmartCart</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(120deg, #f8fafc 60%, #e3f2fd 100%);
            background-image: url('https://img.freepik.com/free-vector/hand-drawn-flat-design-grocery-background_23-2149342942.jpg?w=1200');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .about-box {
            background: rgba(255,255,255,0.92);
            border-radius: 18px;
            box-shadow: 0 2px 16px #e3e3e3;
            padding: 2.5rem 2.2rem;
            max-width: 420px;
            text-align: center;
        }
        .about-box h1 {
            color: var(--primary, #ff9800);
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .about-box p {
            color: var(--gray-text, #555);
            font-size: 1.13rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <span class="logo">SmartCart</span>
        <nav>
            <a href="dashboard.php">Home</a>
            <a href="categories.php">Categories</a>
            <a href="userWishlist.php">Wishlist</a>
            <a href="cart.php">Cart</a>
            <a href="orders.php">Orders</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main style="max-width:900px;margin:2rem auto;">
  <!-- About Section -->
  <section class="max-w-4xl mx-auto mt-10 bg-white shadow-md rounded-xl p-8">
    <h2 class="text-3xl font-bold text-blue-900 mb-4">About Us</h2>
    <p class="mb-4 text-lg">