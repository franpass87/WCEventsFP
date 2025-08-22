# WCEventsFP (v2.1.1) - Enterprise Booking Platform

**Sistema di prenotazione enterprise per competere direttamente con RegionDo, Bokun e GetYourGuide**

Plugin WordPress/WooCommerce completo per eventi ed esperienze con funzionalità di livello enterprise per massimizzare le conversioni, gestire risorse operative e automatizzare la distribuzione multi-canale.

## 🛡️ WSOD Completamente Risolto - v2.1.1

**Il problema della schermata bianca (WSOD) è stato definitivamente risolto!**

- ✅ **Nessun WSOD mai più**: Sistema bulletproof di prevenzione errori
- ✅ **Setup Wizard**: Configurazione guidata per attivazione sicura
- ✅ **Test Pre-Attivazione**: Verifica compatibilità prima dell'attivazione
- ✅ **Documentazione Consolidata**: Tutte le informazioni in un unico posto

📖 **[→ Leggi la Documentazione Completa](DOCUMENTATION.md)**

## 🚀 Funzionalità Principali v2.1.1

### 🛡️ Bulletproof WSOD Prevention System
- **Complete Architecture Simplification**: Single `WCEFP_Simple_Plugin` class eliminates complex dependencies
- **Enhanced WSOD Preventer**: Bulletproof error handling with comprehensive safety checks
- **Graceful Degradation**: Plugin continues working even if WooCommerce is missing
- **Emergency Error System**: Comprehensive error tracking and recovery mechanisms

### 🏗️ Simplified Plugin Architecture  
- **Single Plugin Class**: Singleton pattern eliminates complex loading chains
- **Bulletproof Memory Handling**: Enhanced memory conversion handles all edge cases
- **Streamlined Loading**: Direct class instantiation with multiple fallback systems
- **Reduced Complexity**: Removed unused Bootstrap classes and complex service providers

### 🔧 Enhanced Error Handling & Recovery
- **Unified Error Management**: Consistent error handling approach throughout the plugin
- **Safe Activation/Deactivation**: Removed complex dependency chains, prevents activation errors
- **Memory Safety**: Comprehensive memory limit detection and conversion with overflow protection
- **Critical File Checks**: Validates essential plugin files before initialization

## 🚀 Enterprise Features

### 🏗️ Sistema di Gestione Risorse
Gestione completa di guide, staff, attrezzature, veicoli e location con calendario risorse e prevenzione overbooking.

### 🌐 Distribuzione Multi-Canale  
Integrazione automatica con i principali OTA (Booking.com, Expedia, GetYourGuide, Viator) con sincronizzazione real-time.

### 💰 Sistema Commissioni e Reseller
Programma affiliazione completo con livelli progressivi e dashboard dedicata per partner.

### ⭐ Google Reviews Integration
Connessione diretta con Google Places API per visualizzazione recensioni genuine.

📖 **[→ Vedi Tutte le Funzionalità nella Documentazione Completa](DOCUMENTATION.md)**

## 🚀 Installazione Rapida

### ⚠️ IMPORTANTE: Test Pre-Attivazione (OBBLIGATORIO)

**Prima di attivare il plugin, esegui SEMPRE questo test:**

```bash
cd wp-content/plugins/WCEventsFP
php wcefp-activation-test.php
```

**OPPURE** carica il file via browser:
```
https://tuosito.com/wp-content/plugins/wceventsfp/test-plugin-loading.php
```

### Procedura di Installazione

1. **Test Pre-Attivazione** (vedi sopra)
2. **Attiva Plugin** dalla dashboard WordPress
3. **Segui Setup Wizard** per configurazione guidata
4. **Configura Integrazioni** (Brevo, GA4, Meta Pixel)

## 🛠️ Risoluzione Problemi WSOD

### Se Riscontri WSOD

1. **Disattiva immediatamente** il plugin via FTP:
   ```bash
   cd wp-content/plugins
   mv WCEventsFP WCEventsFP-disabled
   ```

2. **Esegui diagnostica**:
   ```bash
   cd WCEventsFP-disabled  
   php wcefp-health-check.php
   php wcefp-diagnostic-tool.php
   ```

3. **Consulta la documentazione completa**: [DOCUMENTATION.md](DOCUMENTATION.md)

### ✅ Garanzie Anti-WSOD v2.1.1

- **Shutdown Handler**: Cattura errori fatali prima che causino WSOD
- **Emergency Mode**: Modalità ridotta se fallisce caricamento normale  
- **Auto-Deactivation**: Disattivazione automatica in caso di errori critici
- **Setup Wizard**: Configurazione guidata per attivazione sicura

## 📋 Requisiti di Sistema

- **WordPress**: 6.0+
- **WooCommerce**: 7.0+  
- **PHP**: 7.4+
- **Memoria**: 256MB+ raccomandati

## 📞 Supporto

- 📖 **Documentazione Completa**: [DOCUMENTATION.md](DOCUMENTATION.md)
- 📧 **Supporto Email**: Disponibile per problemi tecnici
- 🔧 **Strumenti Diagnostici**: Inclusi nel plugin
- 🆘 **Procedure Emergenza**: Documentate nella guida completa

---

**Autore:** Francesco Passeri  
**Versione:** 2.1.1  
**Testato fino a:** WordPress 6.4 / WooCommerce 8.3