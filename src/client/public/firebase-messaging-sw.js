importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

const firebaseConfig = {
    apiKey: "AIzaSyCYhi0wCvyO6ukp3A8Vvg85E_eeisS6v64",
    authDomain: "pelangi-inventory.firebaseapp.com",
    projectId: "pelangi-inventory",
    storageBucket: "pelangi-inventory.appspot.com",
    messagingSenderId: "560190066215",
    appId: "1:560190066215:web:d77b103d6ef606f37e4802"
};

firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

