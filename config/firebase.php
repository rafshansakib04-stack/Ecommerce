<?php
// Firebase Configuration with Environment Detection
require_once __DIR__ . '/environment.php';

// Get Firebase configuration based on environment
$firebaseConfig = Environment::getFirebaseConfig();

define('FIREBASE_PROJECT_ID', $firebaseConfig['project_id']);
define('FIREBASE_API_KEY', $firebaseConfig['api_key']);
define('FIREBASE_AUTH_DOMAIN', $firebaseConfig['auth_domain']);
define('FIREBASE_DATABASE_URL', $firebaseConfig['database_url']);

class FirebaseService {
    private $apiKey;
    private $projectId;
    private $authDomain;
    private $databaseUrl;
    
    public function __construct() {
        $this->apiKey = FIREBASE_API_KEY;
        $this->projectId = FIREBASE_PROJECT_ID;
        $this->authDomain = FIREBASE_AUTH_DOMAIN;
        $this->databaseUrl = FIREBASE_DATABASE_URL;
    }
    
    public function sendNotification($userId, $title, $message, $data = []) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $headers = [
            'Authorization: key=' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $payload = [
            'to' => $userId,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'icon' => 'https://your-domain.com/assets/images/icon.png'
            ],
            'data' => $data
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function updateRealtimeData($path, $data) {
        $url = $this->databaseUrl . $path . '.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function getRealtimeData($path) {
        $url = $this->databaseUrl . $path . '.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
?>