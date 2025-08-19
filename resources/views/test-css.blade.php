<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSS Test Page</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <div class="bg-primary text-white text-center py-4 px-6">
        <h1 class="text-2xl font-bold">CSS Test Page</h1>
        <p class="text-lg mt-2">If you see this styled, CSS is working!</p>
    </div>
    
    <div class="container mx-auto mt-8 p-4">
        <div class="bg-white shadow-sm border rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Test Elements</h2>
            
            <div class="space-y-4">
                <div class="flex items-center space-x-4">
                    <div class="w-4 h-4 bg-primary rounded"></div>
                    <span class="text-gray-600">Primary color box (should be green)</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="w-4 h-4 bg-accent-500 rounded"></div>
                    <span class="text-gray-600">Accent color box (should be blue)</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="w-4 h-4 bg-gray-200 rounded"></div>
                    <span class="text-gray-600">Gray box</span>
                </div>
            </div>
            
            <div class="mt-6">
                <button class="bg-primary text-white px-4 py-2 rounded hover:bg-primary-600 transition">
                    Test Button
                </button>
            </div>
        </div>
    </div>
</body>
</html>
