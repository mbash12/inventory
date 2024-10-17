// firebase.js

import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";

const firebaseConfig = {
    apiKey: "AIzaSyCYhi0wCvyO6ukp3A8Vvg85E_eeisS6v64",
    authDomain: "pelangi-inventory.firebaseapp.com",
    projectId: "pelangi-inventory",
    storageBucket: "pelangi-inventory.appspot.com",
    messagingSenderId: "560190066215",
    appId: "1:560190066215:web:d77b103d6ef606f37e4802",
};

const firebaseApp = initializeApp(firebaseConfig);
const messaging = getMessaging(firebaseApp);

export const getTokens = () => {
    return new Promise((resolve, reject) => {
        getToken(messaging, { vapidKey: "BGg1hoUjyRMgNEbaIS6EEoVfqfffWJ2GeRPRXOFRgkVbpePed55MzDXPb0NO67jBHQA4Ik2k5a2_7KSPtodJsGs" })
            .then((currentToken) => {
                if (currentToken) {
                    // console.log("Got FCM registration token:", currentToken);
                    resolve(currentToken);
                } else {
                    console.log(
                        "No registration token available. Request permission to generate one."
                    );
                    resolve(false);
                }
            })
            .catch((err) => {
                // console.log("An error occurred while retrieving token. ", err);
                resolve(false);
            });
    }) 
};


export const onMessageListener = (callback) => onMessage(messaging, callback);
// export const onMessageListener = () =>
//     new Promise((resolve) => {
//         onMessage(messaging, (payload) => {
//             resolve(payload);
//         });
//     });
