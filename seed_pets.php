<?php
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Clear existing pets (optional, let's just empty it to avoid duplicates if they run it twice)
    $db->exec("TRUNCATE TABLE pets");

    // Get all customers
    $stmt = $db->query("SELECT customer_id FROM customers");
    $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($customers)) {
        echo "No customers found to seed pets for!\n";
        exit;
    }

    $petNames = ['Milo', 'Luna', 'Bella', 'Charlie', 'Lucy', 'Max', 'Rocky', 'Coco', 'Oreo', 'Leo', 'Simba', 'Buster', 'Tiger', 'Nala', 'Chloe'];
    $dogBreeds = ['Golden Retriever', 'Pug', 'Beagle', 'Bulldog', 'Poodle', 'Chihuahua', 'Siberian Husky'];
    $catBreeds = ['Persian', 'Siamese', 'Maine Coon', 'Scottish Fold', 'British Shorthair', 'Sphynx'];
    $birdBreeds = ['Parrot', 'Cockatiel', 'Canary', 'Lovebird'];
    
    $types = ['Dog', 'Cat', 'Dog', 'Cat', 'Bird', 'Rabbit']; // Weighted towards dogs/cats

    $totalPetsSeeded = 0;

    $insertStmt = $db->prepare("INSERT INTO pets (customer_id, pet_name, pet_type, breed, birthdate, weight, allergy_info) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($customers as $cid) {
        // Decide how many pets this customer gets (1 to 3)
        $numPets = rand(1, 3);
        
        for ($i = 0; $i < $numPets; $i++) {
            $name = $petNames[array_rand($petNames)];
            $type = $types[array_rand($types)];
            
            $breed = null;
            if ($type == 'Dog') {
                $breed = $dogBreeds[array_rand($dogBreeds)];
                $weight = rand(30, 300) / 10; // 3.0 to 30.0 kg
            } elseif ($type == 'Cat') {
                $breed = $catBreeds[array_rand($catBreeds)];
                $weight = rand(20, 80) / 10; // 2.0 to 8.0 kg
            } elseif ($type == 'Bird') {
                $breed = $birdBreeds[array_rand($birdBreeds)];
                $weight = rand(1, 10) / 10; // 0.1 to 1.0 kg
            } else {
                $weight = rand(10, 40) / 10; // 1.0 to 4.0 kg
            }

            // Generate birthdate within last 8 years
            $daysAgo = rand(100, 3000);
            $birthdate = date('Y-m-d', strtotime("-$daysAgo days"));

            // 20% chance of allergy
            $allergy = (rand(1, 10) > 8) ? "Seafood allergy" : null;

            $insertStmt->execute([$cid, $name, $type, $breed, $birthdate, $weight, $allergy]);
            $totalPetsSeeded++;
        }
    }

    echo "Successfully seeded $totalPetsSeeded pets across " . count($customers) . " customers!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
