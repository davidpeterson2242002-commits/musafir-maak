package com.musafirmaak.musafir_maak

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity

// Copied over the generated MainActivity by the APK workflow.
// Creates the "default" notification channel the server sends to (FcmPushGateway),
// with high importance so booking and chat notifications pop up.
class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel("default", "إشعارات مسافر معاك", NotificationManager.IMPORTANCE_HIGH)
            channel.description = "الحجوزات، الارساليات، الرسائل وتنبيهات الحساب"
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }
}
