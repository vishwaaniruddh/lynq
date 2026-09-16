<?php
require_once 'C:/xampp/htdocs/lynq/config/autoload.php';
require_once 'C:/xampp/htdocs/lynq/services/JWTService.php';

$jwtService = new JWTService();
$token = $jwtService->createAccessToken([
    'id' => 47886,
    'username' => 'Vikas Pal',
    'email' => 'vikaspal@gmail.com',
    'role_id' => 3
]);

$ch = curl_init('http://localhost/lynq/api/app/feasibility.php?action=upload');
$cfile = new CURLFile('C:/xampp/htdocs/lynq/uploads/feasibility/1/backroom_network_snap_20251231215658_a10fe965.jpg', 'image/jpeg', 'live_upload_test.jpg');

curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'action' => 'upload',
    'feasibility_id' => 53639,
    'category' => 'backroom_network_snap',
    'file' => $cfile
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $res\n";
