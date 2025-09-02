<?php
/**
 * Firebase Configuration and Helper Functions
 * Handles Firebase Authentication, Firestore, and Cloud Messaging
 */

require_once __DIR__ . '/database.php';

class FirebaseConfig {
    
    public static function getClientConfig() {
        return [
            'apiKey' => FIREBASE_API_KEY,
            'authDomain' => FIREBASE_AUTH_DOMAIN,
            'projectId' => FIREBASE_PROJECT_ID,
            'storageBucket' => FIREBASE_STORAGE_BUCKET,
            'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
            'appId' => FIREBASE_APP_ID,
            'measurementId' => FIREBASE_MEASUREMENT_ID
        ];
    }

    public static function getJavaScriptConfig() {
        $config = self::getClientConfig();
        return json_encode($config, JSON_UNESCAPED_SLASHES);
    }

    public static function renderFirebaseScript() {
        $config = self::getJavaScriptConfig();
        return "
        <!-- Firebase SDK -->
        <script type=\"module\">
            import { initializeApp } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-app.js';
            import { getAuth, signInWithPopup, GoogleAuthProvider, FacebookAuthProvider, signOut, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-auth.js';
            import { getFirestore, doc, setDoc, getDoc, collection, addDoc, updateDoc, deleteDoc, query, where, orderBy, limit, getDocs } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-firestore.js';
            import { getAnalytics, logEvent } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-analytics.js';
            import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging.js';

            // Firebase configuration
            const firebaseConfig = {$config};

            // Initialize Firebase
            const app = initializeApp(firebaseConfig);
            const auth = getAuth(app);
            const db = getFirestore(app);
            const analytics = getAnalytics(app);
            const messaging = getMessaging(app);

            // Make Firebase services globally available
            window.firebaseApp = app;
            window.firebaseAuth = auth;
            window.firebaseDB = db;
            window.firebaseAnalytics = analytics;
            window.firebaseMessaging = messaging;

            // Authentication providers
            const googleProvider = new GoogleAuthProvider();
            googleProvider.setCustomParameters({
                'prompt': 'select_account'
            });

            const facebookProvider = new FacebookAuthProvider();
            facebookProvider.setCustomParameters({
                'display': 'popup'
            });

            window.googleProvider = googleProvider;
            window.facebookProvider = facebookProvider;

            // Firebase helper functions
            window.FirebaseHelpers = {
                // Authentication helpers
                signInWithGoogle: async () => {
                    try {
                        const result = await signInWithPopup(auth, googleProvider);
                        return result.user;
                    } catch (error) {
                        console.error('Google sign-in error:', error);
                        throw error;
                    }
                },

                signInWithFacebook: async () => {
                    try {
                        const result = await signInWithPopup(auth, facebookProvider);
                        return result.user;
                    } catch (error) {
                        console.error('Facebook sign-in error:', error);
                        throw error;
                    }
                },

                signOut: async () => {
                    try {
                        await signOut(auth);
                        return true;
                    } catch (error) {
                        console.error('Sign-out error:', error);
                        throw error;
                    }
                },

                // Firestore helpers
                saveUserData: async (uid, userData) => {
                    try {
                        await setDoc(doc(db, 'users', uid), userData, { merge: true });
                        return true;
                    } catch (error) {
                        console.error('Error saving user data:', error);
                        throw error;
                    }
                },

                getUserData: async (uid) => {
                    try {
                        const docRef = doc(db, 'users', uid);
                        const docSnap = await getDoc(docRef);
                        return docSnap.exists() ? docSnap.data() : null;
                    } catch (error) {
                        console.error('Error getting user data:', error);
                        throw error;
                    }
                },

                // Analytics helpers
                trackEvent: (eventName, parameters = {}) => {
                    try {
                        logEvent(analytics, eventName, parameters);
                    } catch (error) {
                        console.error('Analytics error:', error);
                    }
                },

                // Push notification helpers
                requestNotificationPermission: async () => {
                    try {
                        const permission = await Notification.requestPermission();
                        if (permission === 'granted') {
                            const token = await getToken(messaging, {
                                vapidKey: 'BCdwtS8LJi4ndxNN-3UTEM3M7hIonQcwSiwGCiuEDqNvW_becu92sYW2PYXJhJJETA6hFGSIvXHQ7uVadQrYGyI'
                            });
                            return token;
                        }
                        return null;
                    } catch (error) {
                        console.error('Error getting notification permission:', error);
                        return null;
                    }
                },

                // Real-time data sync
                syncOrderData: async (orderId, orderData) => {
                    try {
                        await setDoc(doc(db, 'orders', orderId.toString()), orderData, { merge: true });
                        return true;
                    } catch (error) {
                        console.error('Error syncing order data:', error);
                        throw error;
                    }
                },

                syncInventoryData: async (productId, inventoryData) => {
                    try {
                        await setDoc(doc(db, 'inventory', productId.toString()), inventoryData, { merge: true });
                        return true;
                    } catch (error) {
                        console.error('Error syncing inventory data:', error);
                        throw error;
                    }
                }
            };

            // Authentication state listener
            onAuthStateChanged(auth, async (user) => {
                if (user) {
                    // User is signed in
                    console.log('User signed in:', user.uid);
                    
                    // Sync with backend
                    try {
                        const response = await fetch('/api/auth/firebase-sync.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                uid: user.uid,
                                email: user.email,
                                name: user.displayName,
                                photo: user.photoURL,
                                provider: user.providerData[0]?.providerId
                            })
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            if (result.success) {
                                // Store user session
                                localStorage.setItem('user_session', JSON.stringify(result.user));
                                
                                // Redirect if needed
                                if (result.redirect) {
                                    window.location.href = result.redirect;
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Backend sync error:', error);
                    }
                } else {
                    // User is signed out
                    console.log('User signed out');
                    localStorage.removeItem('user_session');
                }
            });

            // Handle incoming messages when app is in foreground
            onMessage(messaging, (payload) => {
                console.log('Message received in foreground:', payload);
                
                // Show notification
                if (payload.notification) {
                    new Notification(payload.notification.title, {
                        body: payload.notification.body,
                        icon: payload.notification.icon || '/assets/images/logo.png'
                    });
                }
                
                // Handle data payload
                if (payload.data) {
                    // Update UI based on notification data
                    window.handleFirebaseNotification(payload.data);
                }
            });

            console.log('Firebase initialized successfully');
        </script>
        ";
    }
}

// PHP Firebase Admin SDK helper functions
class FirebaseAdmin {
    private static $serviceAccount = null;
    
    public static function initializeAdmin() {
        // This would typically use the Firebase Admin SDK
        // For now, we'll use REST API calls
        return true;
    }
    
    public static function verifyIdToken($idToken) {
        // Verify Firebase ID token
        $url = "https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com";
        
        // In production, implement proper JWT verification
        // For now, basic validation
        if (empty($idToken)) {
            return false;
        }
        
        // Decode JWT (simplified - use proper library in production)
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return false;
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        
        // Verify issuer and audience
        if ($payload['iss'] !== 'https://securetoken.google.com/' . FIREBASE_PROJECT_ID ||
            $payload['aud'] !== FIREBASE_PROJECT_ID) {
            return false;
        }
        
        // Check expiration
        if ($payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    public static function createCustomToken($uid, $claims = []) {
        // Create custom token for Firebase
        // In production, use Firebase Admin SDK
        return null;
    }
    
    public static function sendNotification($tokens, $notification, $data = []) {
        // Send push notification via FCM
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $headers = [
            'Authorization: key=' . 'YOUR_SERVER_KEY_HERE', // Set your FCM server key
            'Content-Type: application/json'
        ];
        
        $payload = [
            'registration_ids' => is_array($tokens) ? $tokens : [$tokens],
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200 ? json_decode($result, true) : false;
    }
}

// Initialize Firebase Admin
FirebaseAdmin::initializeAdmin();
?>