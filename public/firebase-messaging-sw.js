// public/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/9.17.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.17.2/firebase-messaging-compat.js');

// Initialize Firebase
const firebaseConfig = {
    apiKey: "AIzaSyBl9WH__ZWoWy6xhpk7D4S65gwsHX621IM",
    authDomain: "chatapp-76c82.firebaseapp.com",
    projectId: "chatapp-76c82",
    storageBucket: "chatapp-76c82.appspot.com",
    messagingSenderId: "218928639013",
    appId: "1:218928639013:web:b66ba3692347ca296763cc"
};

firebase.initializeApp(firebaseConfig);

// Retrieve an instance of Firebase Messaging
const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage(function(payload) {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);
    const notificationTitle = payload.notification.title;
    const notificationOptions = {
        body: payload.notification.body,
        icon: payload.notification.icon || 'https://chat.yellownft.xyz/build/assets/logo2.25baa396.svg',
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});