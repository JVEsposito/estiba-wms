package expo.modules.estibaprintersocket

import expo.modules.kotlin.modules.Module
import expo.modules.kotlin.modules.ModuleDefinition
import java.io.BufferedOutputStream
import java.net.InetSocketAddress
import java.net.Socket

class EstibaPrinterSocketModule : Module() {
  override fun definition() = ModuleDefinition {
    Name("EstibaPrinterSocket")

    AsyncFunction("testConnectionAsync") { host: String, port: Int, timeoutMs: Int ->
      validateDestination(host, port, timeoutMs)
      try {
        Socket().use { socket ->
          socket.connect(InetSocketAddress(host, port), timeoutMs)
        }
        mapOf(
          "status" to "connected",
          "bytesSent" to 0,
          "message" to "Conexión establecida",
        )
      } catch (error: Exception) {
        mapOf(
          "status" to "failed",
          "bytesSent" to 0,
          "message" to safeMessage(error),
        )
      }
    }

    AsyncFunction("sendAsync") { host: String, port: Int, payload: String, timeoutMs: Int ->
      validateDestination(host, port, timeoutMs)
      require(payload.isNotEmpty()) { "El contenido de impresión está vacío." }
      require(payload.toByteArray(Charsets.UTF_8).size <= MAX_PAYLOAD_BYTES) {
        "El contenido de impresión supera el máximo permitido."
      }

      val bytes = payload.toByteArray(Charsets.UTF_8)
      var connected = false
      var writeStarted = false
      try {
        Socket().use { socket ->
          socket.connect(InetSocketAddress(host, port), timeoutMs)
          connected = true
          socket.soTimeout = timeoutMs
          BufferedOutputStream(socket.getOutputStream()).use { output ->
            writeStarted = true
            output.write(bytes)
            output.flush()
          }
        }
        mapOf(
          "status" to "sent",
          "bytesSent" to bytes.size,
          "message" to "Datos enviados a la impresora",
        )
      } catch (error: Exception) {
        val uncertain = connected && writeStarted
        mapOf(
          "status" to if (uncertain) "indeterminate" else "failed",
          "bytesSent" to 0,
          "message" to safeMessage(error),
        )
      }
    }
  }

  private fun validateDestination(host: String, port: Int, timeoutMs: Int) {
    require(IPV4.matches(host)) { "La IP de la impresora no es válida." }
    require(port in 1..65535) { "El puerto de la impresora no es válido." }
    require(timeoutMs in 500..30000) { "El tiempo máximo de conexión no es válido." }
  }

  private fun safeMessage(error: Exception): String {
    return error.message?.take(500) ?: error.javaClass.simpleName
  }

  companion object {
    private const val MAX_PAYLOAD_BYTES = 5_000_000
    private val IPV4 = Regex(
      """^(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)$""",
    )
  }
}
