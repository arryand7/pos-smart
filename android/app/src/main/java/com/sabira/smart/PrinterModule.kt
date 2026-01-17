package com.sabira.smart

import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.util.Log
import android.webkit.JavascriptInterface
import java.io.OutputStream
import java.util.UUID

/**
 * PrinterModule - ESC/POS Bluetooth Thermal Printer Module
 * 
 * This module provides JavaScript interface for printing receipts
 * via Bluetooth thermal printers using ESC/POS commands.
 */
class PrinterModule(private val activity: MainActivity) {
    
    companion object {
        private const val TAG = "PrinterModule"
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }
    
    private var bluetoothAdapter: BluetoothAdapter? = BluetoothAdapter.getDefaultAdapter()
    private var bluetoothSocket: BluetoothSocket? = null
    private var outputStream: OutputStream? = null
    private var connectedDevice: BluetoothDevice? = null
    
    /**
     * Get list of paired Bluetooth devices
     */
    @JavascriptInterface
    fun getPairedDevices(): String {
        if (bluetoothAdapter == null) {
            return """{"error": "Bluetooth tidak tersedia", "devices": []}"""
        }
        
        val pairedDevices = bluetoothAdapter?.bondedDevices ?: emptySet()
        val deviceList = pairedDevices.map { device ->
            """{"name": "${device.name ?: "Unknown"}", "address": "${device.address}"}"""
        }
        
        return """{"devices": [${deviceList.joinToString(",")}]}"""
    }
    
    /**
     * Connect to a Bluetooth printer by MAC address
     */
    @JavascriptInterface
    fun connect(address: String): String {
        try {
            if (bluetoothAdapter == null) {
                return """{"success": false, "error": "Bluetooth tidak tersedia"}"""
            }
            
            disconnect()
            
            val device = bluetoothAdapter?.getRemoteDevice(address)
            if (device == null) {
                return """{"success": false, "error": "Perangkat tidak ditemukan"}"""
            }
            
            bluetoothSocket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            bluetoothSocket?.connect()
            outputStream = bluetoothSocket?.outputStream
            connectedDevice = device
            
            Log.d(TAG, "Connected to ${device.name}")
            return """{"success": true, "device": "${device.name}"}"""
            
        } catch (e: Exception) {
            Log.e(TAG, "Connection failed", e)
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Disconnect from the current printer
     */
    @JavascriptInterface
    fun disconnect(): String {
        try {
            outputStream?.close()
            bluetoothSocket?.close()
            outputStream = null
            bluetoothSocket = null
            connectedDevice = null
            return """{"success": true}"""
        } catch (e: Exception) {
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Check if connected to a printer
     */
    @JavascriptInterface
    fun isConnected(): Boolean {
        return bluetoothSocket?.isConnected == true
    }
    
    /**
     * Print text with ESC/POS formatting
     */
    @JavascriptInterface
    fun printText(text: String): String {
        try {
            if (outputStream == null) {
                return """{"success": false, "error": "Printer tidak terhubung"}"""
            }
            
            outputStream?.write(text.toByteArray(Charsets.UTF_8))
            return """{"success": true}"""
            
        } catch (e: Exception) {
            Log.e(TAG, "Print failed", e)
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Print a receipt with standard formatting
     * @param storeName Store name for header
     * @param items JSON array of items: [{"name": "", "qty": 0, "price": 0}]
     * @param total Total amount
     * @param paymentMethod Payment method used
     */
    @JavascriptInterface
    fun printReceipt(storeName: String, items: String, total: Double, paymentMethod: String): String {
        try {
            if (outputStream == null) {
                return """{"success": false, "error": "Printer tidak terhubung"}"""
            }
            
            val sb = StringBuilder()
            
            // Initialize printer
            sb.append(byteArrayOf(0x1B, 0x40).toString(Charsets.ISO_8859_1)) // ESC @
            
            // Center align, bold, double size for header
            sb.append(byteArrayOf(0x1B, 0x61, 0x01).toString(Charsets.ISO_8859_1)) // Center
            sb.append(byteArrayOf(0x1B, 0x45, 0x01).toString(Charsets.ISO_8859_1)) // Bold on
            sb.append(byteArrayOf(0x1D, 0x21, 0x11).toString(Charsets.ISO_8859_1)) // Double size
            sb.append("$storeName\n")
            
            // Reset to normal
            sb.append(byteArrayOf(0x1D, 0x21, 0x00).toString(Charsets.ISO_8859_1))
            sb.append(byteArrayOf(0x1B, 0x45, 0x00).toString(Charsets.ISO_8859_1))
            sb.append("SABIRA MART\n")
            sb.append("================================\n")
            
            // Left align for items
            sb.append(byteArrayOf(0x1B, 0x61, 0x00).toString(Charsets.ISO_8859_1))
            
            // Parse and print items (simplified)
            sb.append("Item                    Total\n")
            sb.append("--------------------------------\n")
            // Note: In production, parse JSON items properly
            sb.append(items.replace("[", "").replace("]", "").replace("{", "").replace("}", "\n"))
            
            sb.append("--------------------------------\n")
            sb.append("TOTAL: Rp ${String.format("%,.0f", total)}\n")
            sb.append("Metode: $paymentMethod\n")
            sb.append("================================\n")
            
            // Center for footer
            sb.append(byteArrayOf(0x1B, 0x61, 0x01).toString(Charsets.ISO_8859_1))
            sb.append("Terima kasih!\n")
            sb.append("Semoga berkah.\n\n\n")
            
            // Cut paper (if supported)
            sb.append(byteArrayOf(0x1D, 0x56, 0x00).toString(Charsets.ISO_8859_1))
            
            outputStream?.write(sb.toString().toByteArray(Charsets.ISO_8859_1))
            outputStream?.flush()
            
            return """{"success": true}"""
            
        } catch (e: Exception) {
            Log.e(TAG, "Print receipt failed", e)
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
    
    /**
     * Feed paper
     */
    @JavascriptInterface
    fun feedPaper(lines: Int = 3): String {
        try {
            outputStream?.write("\n".repeat(lines).toByteArray())
            return """{"success": true}"""
        } catch (e: Exception) {
            return """{"success": false, "error": "${e.message}"}"""
        }
    }
}
