<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Test - iruali</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #10b981;
            text-align: center;
            margin-bottom: 30px;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .image-item {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: #f9fafb;
        }
        .image-item img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            margin-bottom: 10px;
        }
        .image-item h3 {
            margin: 10px 0;
            color: #374151;
        }
        .image-item p {
            color: #6b7280;
            font-size: 14px;
        }
        .status {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: bold;
        }
        .status.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖼️ Product Images Test</h1>
        
        <div class="status success">
            ✅ This page tests if all product images are loading correctly
        </div>

        <div class="image-grid">
            <div class="image-item">
                <img src="/images/products/headphones.svg" alt="Headphones">
                <h3>Wireless Bluetooth Headphones</h3>
                <p>Electronics - TechAudio</p>
            </div>
            
            <div class="image-item">
                <img src="/images/products/denim-jacket.svg" alt="T-Shirt">
                <h3>Premium Cotton T-Shirt</h3>
                <p>Fashion - FashionCo</p>
            </div>
            
            <div class="image-item">
                <img src="/images/products/smartphone.svg" alt="Smart Watch">
                <h3>Smart Fitness Watch</h3>
                <p>Electronics - FitTech</p>
            </div>
            
            <div class="image-item">
                <img src="/images/products/coffee-table.svg" alt="Coffee Beans">
                <h3>Organic Coffee Beans</h3>
                <p>Food & Beverage - BrewMaster</p>
            </div>
            
            <div class="image-item">
                <img src="/images/products/laptop.svg" alt="Camera Lens">
                <h3>Professional Camera Lens</h3>
                <p>Electronics - PhotoPro</p>
            </div>
        </div>

        <div class="status success">
            🎯 If you can see all 5 product images above, the website is working perfectly!
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/" style="background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                ← Back to Home
            </a>
        </div>
    </div>
</body>
</html>
