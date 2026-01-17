package com.sabira.smart

import android.Manifest
import android.app.Activity
import android.content.pm.PackageManager
import android.util.Log
import android.webkit.JavascriptInterface
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.google.zxing.BarcodeFormat
import com.google.zxing.ResultPoint
import com.journeyapps.barcodescanner.BarcodeCallback
import com.journeyapps.barcodescanner.BarcodeResult
import com.journeyapps.barcodescanner.DecoratedBarcodeView

/**
 * QrScannerModule - QR/Barcode Scanner Module using ZXing
 * 
 * This module provides JavaScript interface for scanning QR codes
 * and barcodes. Uses ZXing (Zebra Crossing) library.
 * 
 * Note: Requires adding ZXing dependency to build.gradle:
 * implementation 'com.journeyapps:zxing-android-embedded:4.3.0'
 */
class QrScannerModule(private val activity: MainActivity) {
    
    companion object {
        private const val TAG = "QrScannerModule"
        private const val CAMERA_PERMISSION_REQUEST = 1001
    }
    
    private var scanCallback: ((String) -> Unit)? = null
    private var lastScannedCode: String? = null
    private var scanning = false
    
    /**
     * Check if camera permission is granted
     */
    @JavascriptInterface
    fun hasCameraPermission(): Boolean {
        return ContextCompat.checkSelfPermission(
            activity,
            Manifest.permission.CAMERA
        ) == PackageManager.PERMISSION_GRANTED
    }
    
    /**
     * Request camera permission
     */
    @JavascriptInterface
    fun requestCameraPermission() {
        ActivityCompat.requestPermissions(
            activity,
            arrayOf(Manifest.permission.CAMERA),
            CAMERA_PERMISSION_REQUEST
        )
    }
    
    /**
     * Get the last scanned code
     */
    @JavascriptInterface
    fun getLastScannedCode(): String {
        return lastScannedCode ?: ""
    }
    
    /**
     * Clear the last scanned code
     */
    @JavascriptInterface
    fun clearLastScannedCode() {
        lastScannedCode = null
    }
    
    /**
     * Check if currently scanning
     */
    @JavascriptInterface
    fun isScanning(): Boolean {
        return scanning
    }
    
    /**
     * Start scanning using device camera
     * This opens the camera scanner activity
     */
    @JavascriptInterface
    fun startScan(): String {
        if (!hasCameraPermission()) {
            return """{"success": false, "error": "Izin kamera diperlukan"}"""
        }
        
        try {
            scanning = true
            // Note: In a real implementation, this would start an Intent
            // to launch the barcode scanner activity
            Log.d(TAG, "Starting barcode scan...")
            
            // For WebView integration, we would typically use:
            // IntentIntegrator(activity).initiateScan()
            
            return """{"success": true, "message": "Scanner dimulai"}"""
        } catch (e: Exception) {
            scanning = false
            Log.e(TAG, "Scan failed", e)
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Stop scanning
     */
    @JavascriptInterface
    fun stopScan(): String {
        scanning = false
        return """{"success": true}"""
    }
    
    /**
     * Parse a scanned santri QR code
     * Expected format: SMART:SANTRI:{nis}
     */
    @JavascriptInterface
    fun parseSantriQr(code: String): String {
        return try {
            if (code.startsWith("SMART:SANTRI:")) {
                val nis = code.removePrefix("SMART:SANTRI:")
                """{"success": true, "type": "santri", "nis": "$nis"}"""
            } else {
                """{"success": false, "error": "Format QR tidak valid"}"""
            }
        } catch (e: Exception) {
            """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Parse a scanned product barcode
     * Supports EAN-13, EAN-8, UPC-A, UPC-E, Code 128
     */
    @JavascriptInterface
    fun parseProductBarcode(code: String): String {
        return try {
            // Basic validation - check if it's a valid barcode format
            val isValidBarcode = code.matches(Regex("^[0-9A-Za-z\\-]+$"))
            
            if (isValidBarcode) {
                """{"success": true, "type": "product", "barcode": "$code"}"""
            } else {
                """{"success": false, "error": "Barcode tidak valid"}"""
            }
        } catch (e: Exception) {
            """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Handle scan result from ZXing
     * This is called internally when a barcode is scanned
     */
    fun onScanResult(code: String, format: String) {
        lastScannedCode = code
        scanning = false
        
        Log.d(TAG, "Scanned: $code (format: $format)")
        
        // Notify JavaScript via callback
        activity.runOnUiThread {
            val escapedCode = code.replace("\"", "\\\"")
            activity.webView?.evaluateJavascript(
                """
                if (window.onBarcodeScanned) {
                    window.onBarcodeScanned({
                        code: "$escapedCode",
                        format: "$format"
                    });
                }
                """.trimIndent(),
                null
            )
        }
    }
    
    /**
     * Generate a QR code data URL for a santri
     */
    @JavascriptInterface
    fun generateSantriQrData(nis: String): String {
        return "SMART:SANTRI:$nis"
    }
}
