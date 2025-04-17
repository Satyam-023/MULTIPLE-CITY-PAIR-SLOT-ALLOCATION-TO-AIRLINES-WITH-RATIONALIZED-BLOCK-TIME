<?php
// Array of images to download
$images = [
    // Hero Section
    'hero-airplane.jpg' => 'https://images.pexels.com/photos/46148/aircraft-jet-landing-cloud-46148.jpeg',
    
    // Statistics Section
    'happy-travelers.jpg' => 'https://images.pexels.com/photos/3768894/pexels-photo-3768894.jpeg',
    'world-map.jpg' => 'https://images.pexels.com/photos/1105766/pexels-photo-1105766.jpeg',
    'customer-support.jpg' => 'https://images.pexels.com/photos/3184465/pexels-photo-3184465.jpeg',
    'on-time-flight.jpg' => 'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg',
    
    // Features Section
    'best-price.jpg' => 'https://images.pexels.com/photos/3184460/pexels-photo-3184460.jpeg',
    'flexible-booking.jpg' => 'https://images.pexels.com/photos/3184465/pexels-photo-3184465.jpeg',
    'customer-service.jpg' => 'https://images.pexels.com/photos/3184465/pexels-photo-3184465.jpeg',
    
    // Destinations Section
    'new-york.jpg' => 'https://images.pexels.com/photos/1519088/pexels-photo-1519088.jpeg',
    'paris.jpg' => 'https://images.pexels.com/photos/338515/pexels-photo-338515.jpeg',
    'tokyo.jpg' => 'https://images.pexels.com/photos/161963/chicago-illinois-skyline-skyscrapers-161963.jpeg',
    'sydney.jpg' => 'https://images.pexels.com/photos/1796730/pexels-photo-1796730.jpeg',
    
    // Gallery Section
    'business-class.jpg' => 'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg',
    'airport-lounge.jpg' => 'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg',
    'inflight-entertainment.jpg' => 'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg',
    'airline-food.jpg' => 'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg',
    
    // Partners Section
    'emirates-logo.png' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Emirates_logo.svg/1200px-Emirates_logo.svg.png',
    'singapore-airlines-logo.png' => 'https://cdn.freebiesupply.com/logos/large/2x/singapore-airlines-logo-png-transparent.png',
    'qatar-airways-logo.png' => 'https://cdn.freebiesupply.com/logos/large/2x/qatar-airways-logo-png-transparent.png',
    'lufthansa-logo.png' => 'https://cdn.freebiesupply.com/logos/large/2x/lufthansa-logo-png-transparent.png'
];

// Create images directory if it doesn't exist
if (!file_exists('images')) {
    mkdir('images', 0777, true);
}

// Download each image
foreach ($images as $filename => $url) {
    $filepath = 'images/' . $filename;
    
    // Skip if file already exists
    if (file_exists($filepath)) {
        echo "Skipping $filename - already exists\n";
        continue;
    }
    
    // Download the image
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        echo "Failed to download $filename\n";
        continue;
    }
    
    // Save the image
    if (file_put_contents($filepath, $imageData)) {
        echo "Successfully downloaded $filename\n";
    } else {
        echo "Failed to save $filename\n";
    }
    
    // Add a small delay to avoid overwhelming the server
    usleep(500000); // 0.5 second delay
}

echo "Download process completed!\n";
?> 