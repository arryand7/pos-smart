package com.sabira.smart

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.print.PrintManager
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.result.ActivityResultLauncher
import androidx.appcompat.app.AppCompatActivity
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var scanLauncher: ActivityResultLauncher<ScanOptions>
    private var pendingScanMode: String = "product"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webview)

        scanLauncher = registerForActivityResult(ScanContract()) { result ->
            if (result.contents.isNullOrEmpty()) {
                Toast.makeText(this, "Scan dibatalkan.", Toast.LENGTH_SHORT).show()
            } else {
                dispatchScanResult(result.contents, pendingScanMode)
            }
        }

        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.cacheMode = WebSettings.LOAD_DEFAULT
        settings.userAgentString = settings.userAgentString + " SMARTAndroid"

        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG)

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val url = request.url
                val scheme = url.scheme ?: return false

                if (scheme == "http" || scheme == "https") {
                    return false
                }

                startActivity(Intent(Intent.ACTION_VIEW, url))
                return true
            }
        }

        webView.webChromeClient = WebChromeClient()
        webView.addJavascriptInterface(SmartBridge(), "SmartAndroid")
        webView.loadUrl(getString(R.string.web_app_url))
    }

    override fun onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }

    override fun onDestroy() {
        webView.destroy()
        super.onDestroy()
    }

    private fun dispatchScanResult(code: String, mode: String) {
        val safeCode = escapeForJs(code)
        val safeMode = escapeForJs(mode)
        val script = "window.dispatchEvent(new CustomEvent('smart:scan', { detail: { code: '$safeCode', mode: '$safeMode' } }));"
        webView.post { webView.evaluateJavascript(script, null) }
    }

    private fun escapeForJs(value: String): String {
        return value
            .replace("\\\\", "\\\\\\\\")
            .replace("'", "\\\\'")
            .replace("\\n", "\\\\n")
            .replace("\\r", "")
    }

    private fun printHtml(html: String) {
        val printWebView = WebView(this)
        printWebView.settings.javaScriptEnabled = false
        printWebView.loadDataWithBaseURL(null, html, "text/html", "utf-8", null)
        printWebView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView, url: String) {
                val printManager = getSystemService(PRINT_SERVICE) as PrintManager
                val adapter = view.createPrintDocumentAdapter("SMART Receipt")
                printManager.print("SMART Receipt", adapter, null)
            }
        }
    }

    inner class SmartBridge {
        @JavascriptInterface
        fun startScan(mode: String?) {
            runOnUiThread {
                pendingScanMode = mode ?: "product"
                val options = ScanOptions()
                options.setDesiredBarcodeFormats(ScanOptions.ALL_CODE_TYPES)
                options.setPrompt("Scan barcode / QR")
                options.setBeepEnabled(true)
                options.setOrientationLocked(false)
                scanLauncher.launch(options)
            }
        }

        @JavascriptInterface
        fun printReceipt(html: String?) {
            runOnUiThread {
                if (html.isNullOrBlank()) {
                    Toast.makeText(this@MainActivity, "Struk kosong.", Toast.LENGTH_SHORT).show()
                } else {
                    printHtml(html)
                }
            }
        }
    }
}
