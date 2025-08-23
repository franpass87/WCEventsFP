# 📦 COME SCARICARE E INSTALLARE WCEventsFP - GUIDA PER UTENTI

> **⚠️ ATTENZIONE:** Se non sei un programmatore, **NON** usare il bottone verde "Code → Download ZIP" su GitHub!

## 🎯 PROBLEMA RISOLTO

**Prima (problema):**
- Scaricare "Code → Download ZIP" da GitHub
- Il plugin non funzionava perché mancavano le dipendenze
- Errori di attivazione, WSOD (schermo bianco)

**Ora (soluzione):**
- Download di un ZIP già pronto con tutto incluso
- Plugin funziona immediatamente dopo l'installazione
- Zero configurazione richiesta

---

## 🚀 METODO 1: DOWNLOAD RACCOMANDATO (GitHub Releases)

### ✅ CORRETTO - Usa le Release

1. **Vai nella sezione Releases:**
   ```
   https://github.com/franpass87/WCEventsFP/releases
   ```

2. **Scarica il file ZIP dalla release più recente:**
   - Cerca l'ultima versione (es: v2.1.2)
   - Scarica il file `wceventsfp-2.1.1.zip` (o versione simile)
   - **NON** scaricare "Source code (zip)"

3. **Il file ZIP contiene tutto il necessario:**
   - ✅ Tutte le dipendenze incluse
   - ✅ Nessun Composer richiesto
   - ✅ Sistema anti-WSOD attivo
   - ✅ Pronto per WordPress

---

## 🚀 METODO 2: BUILD MANUALE (Solo se necessario)

Se non ci sono release disponibili, puoi creare il ZIP tu stesso:

### Su Linux/Mac:
```bash
# Scarica il repository
git clone https://github.com/franpass87/WCEventsFP.git
cd WCEventsFP

# Crea il ZIP di distribuzione
./build-distribution.sh

# Usa il file wceventsfp-X.X.X.zip generato
```

### Su Windows:
1. Scarica il repository come ZIP
2. Estrai tutto in una cartella
3. Elimina manualmente questi file/cartelle:
   - `.git/`
   - `.github/` 
   - `tests/`
   - `node_modules/`
   - `composer.json`
   - `composer.lock`
   - `package.json`
   - `phpunit.xml`
   - `phpcs.xml`
4. Ri-comprimi tutto in un nuovo ZIP

---

## 📥 INSTALLAZIONE IN WORDPRESS

1. **Vai in WordPress Admin → Plugin → Aggiungi Nuovo**

2. **Clicca "Carica Plugin"**

3. **Seleziona il file ZIP scaricato/creato:**
   - `wceventsfp-2.1.1.zip` (o versione corrente)
   - **NON** il file "Source code" di GitHub

4. **Clicca "Installa Ora"**

5. **Attiva il plugin**

---

## ❌ COSA NON FARE

### ⛔ NON usare "Code → Download ZIP" di GitHub
```
❌ https://github.com/franpass87/WCEventsFP → Code → Download ZIP
```
**Perché non funziona:**
- È codice sorgente, non una distribuzione
- Mancano le dipendenze necessarie  
- Richiede Composer per funzionare
- Può causare errori di attivazione

### ⛔ NON scaricare "Source code (zip)" dalle Release
```
❌ Release → Source code (zip)
```
**Usa invece:**
```
✅ Release → wceventsfp-X.X.X.zip
```

---

## 🛡️ SISTEMA ANTI-WSOD INTEGRATO

WCEventsFP v2.1.2+ include protezioni avanzate:

- ✅ **Nessuna dipendenza da Composer** - funziona sempre
- ✅ **Autoloader personalizzato** - carica tutte le classi
- ✅ **Sistema anti-WSOD** - previene schermi bianchi
- ✅ **Fallback automatici** - si adatta a server limitati
- ✅ **Test pre-attivazione** - verifica compatibilità

### Se hai problemi:
```bash
# Test pre-attivazione (opzionale)
php wp-content/plugins/wceventsfp/tools/diagnostics/test-plugin-loading.php
```

---

## 🆘 SUPPORTO

Se hai scaricato il plugin correttamente ma hai ancora problemi:

1. **Verifica che hai scaricato il ZIP giusto:**
   - ✅ Da GitHub Releases: `wceventsfp-X.X.X.zip`
   - ❌ NON da "Code → Download ZIP"

2. **Controlla i requisiti:**
   - WordPress 5.0+
   - WooCommerce 5.0+  
   - PHP 7.4+

3. **Test diagnostico:**
   - Usa i tool in `tools/diagnostics/`

4. **Contatta il supporto con:**
   - Versione WordPress/WooCommerce/PHP
   - Messaggio di errore esatto
   - Come hai scaricato il plugin

---

## 📋 RIEPILOGO VELOCE

**Per utenti non-programmatori:**

1. ✅ Vai su: https://github.com/franpass87/WCEventsFP/releases
2. ✅ Scarica: `wceventsfp-X.X.X.zip` 
3. ✅ Installa in WordPress normalmente
4. ❌ NON usare "Code → Download ZIP"

**Il plugin funzionerà immediatamente senza configurazioni aggiuntive!**