<?php
// Run ONCE from the project folder:   php seed.php
// Optional:  php seed.php "you@email.com" "YourStrongPassword" "Your Name"
// Creates the ADMIN account + sample services and veterinarians. Safe to run again (it skips what exists).

if (PHP_SAPI !== 'cli') exit('Run this from the command line: php seed.php');

require __DIR__ . '/app/bootstrap.php';

$email = strtolower($argv[1] ?? 'admin@petcare.com');
$pass  = $argv[2] ?? 'Admin@12345';
$name  = $argv[3] ?? 'Clinic Administrator';

echo "Connecting to MongoDB...\n";
ensure_indexes();

$existing = col('users')->findOne(['email' => $email]);
if ($existing) {
    echo "• Admin account already exists: $email (not changed)\n";
} else {
    col('users')->insertOne([
        'name' => $name, 'email' => $email, 'phone' => '', 'address' => '',
        'password' => password_hash($pass, PASSWORD_DEFAULT),
        'role' => 'admin', 'active' => true, 'created_at' => utc_now(),
    ]);
    echo "✔ Admin account created\n    Email:    $email\n    Password: $pass\n    (Sign in at /login.php, then change the password under My Account.)\n";
}

if (col('services')->countDocuments([]) === 0) {
    $services = [
        ['General Check-up', 'Preventive care', 'Full physical examination and health consultation.', 500, 30],
        ['Anti-rabies Vaccination', 'Vaccination', 'Rabies vaccine with vaccination card.', 350, 30],
        ['5-in-1 / 8-in-1 Vaccination', 'Vaccination', 'Core vaccine for dogs and cats.', 800, 30],
        ['Deworming', 'Preventive care', 'Oral deworming based on weight.', 250, 30],
        ['Grooming (Basic)', 'Grooming', 'Bath, blow-dry, nail trim and ear cleaning.', 600, 60],
        ['Spay / Neuter Consultation', 'Surgery', 'Pre-surgical assessment and scheduling.', 700, 30],
        ['Dental Cleaning Consultation', 'Dental', 'Dental exam and cleaning plan.', 900, 30],
        ['Laboratory / Blood Test', 'Diagnostics', 'Complete blood count and basic chemistry.', 1500, 30],
        ['Emergency Consultation', 'Emergency', 'Urgent evaluation and stabilization.', 1200, 30],
    ];
    foreach ($services as [$n, $cat, $desc, $price, $dur]) {
        col('services')->insertOne(['name' => $n, 'category' => $cat, 'description' => $desc, 'price' => (float)$price, 'duration' => $dur, 'active' => true, 'created_at' => utc_now()]);
    }
    echo "✔ " . count($services) . " sample services added (prices in ₱)\n";
} else {
    echo "• Services already exist — skipped\n";
}

if (col('vets')->countDocuments([]) === 0) {
    $vets = [
        ['Dr. Maria Santos', 'Small animal medicine', 'PRC-0012345', 'Dedicated to preventive care and gentle handling for dogs and cats.'],
        ['Dr. Jose Reyes', 'Surgery & dentistry', 'PRC-0023456', 'Experienced in soft-tissue surgery and dental procedures.'],
    ];
    foreach ($vets as [$n, $spec, $lic, $bio]) {
        col('vets')->insertOne([
            'name' => $n, 'specialization' => $spec, 'license_no' => $lic, 'email' => '', 'phone' => '', 'bio' => $bio,
            'days' => [1, 2, 3, 4, 5, 6], 'start' => '09:00', 'end' => '17:00', 'active' => true, 'created_at' => utc_now(),
        ]);
    }
    echo "✔ " . count($vets) . " sample veterinarians added (edit them in Admin → Veterinarians)\n";
} else {
    echo "• Veterinarians already exist — skipped\n";
}

echo "\nDone. Start the site with:  php -S localhost:8000 -t public\n";
