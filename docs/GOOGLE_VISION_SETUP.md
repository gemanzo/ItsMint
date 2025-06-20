# 🔍 Google Vision API Setup Guide

Questa guida ti aiuterà a configurare Google Cloud Vision API per il sistema OCR di ItsMint.

## 🚀 1. Setup Google Cloud Console

### 1.1 Crea/Seleziona Progetto Google Cloud

1. Vai su [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuovo progetto o seleziona uno esistente
3. Nome suggerito: `itsmint-ocr` o simile

### 1.2 Abilita Vision API

1. Nel menu di navigazione, vai su **APIs & Services > Library**
2. Cerca **"Cloud Vision API"**
3. Clicca **"Enable"**

### 1.3 Crea Service Account

1. Vai su **APIs & Services > Credentials**
2. Clicca **"Create Credentials" > "Service Account"**
3. Compila i dettagli:
   - **Name**: `itsmint-ocr-service`
   - **Description**: `Service account for OCR functionality`
4. Clicca **"Create and Continue"**
5. Assegna il ruolo: **"Cloud Vision API User"**
6. Clicca **"Continue"** e poi **"Done"**

### 1.4 Scarica Service Account Key

1. Nella lista delle credenziali, clicca sul service account appena creato
2. Vai alla tab **"Keys"**
3. Clicca **"Add Key" > "Create new key"**
4. Seleziona **JSON** e clicca **"Create"**
5. Il file JSON verrà scaricato automaticamente
6. **IMPORTANTE**: Salva questo file in un posto sicuro!

## ⚙️ 2. Configurazione Laravel

### 2.1 Variabili Environment

Aggiungi queste variabili al tuo file `.env`:

```bash
# Google Cloud Vision API Configuration
GOOGLE_CLOUD_PROJECT_ID=il-tuo-project-id
GOOGLE_APPLICATION_CREDENTIALS=/path/assoluto/al/service-account-key.json
GOOGLE_VISION_ENABLED=true

# OCR Debug Settings (opzionali)
OCR_LOG_RESULTS=true
OCR_LOG_SEARCH=true
OCR_LOG_VISION=true
OCR_SAVE_PROCESSED=false
```

### 2.2 Posizionamento Service Account Key

**Opzione A: Directory del progetto (consigliata per sviluppo)**
```bash
mkdir storage/credentials
cp ~/Downloads/service-account-key.json storage/credentials/google-vision.json
```

Poi nel `.env`:
```bash
GOOGLE_APPLICATION_CREDENTIALS=/path/completo/al/progetto/storage/credentials/google-vision.json
```

**Opzione B: Directory sistema (consigliata per produzione)**
```bash
# Su server Linux
sudo mkdir -p /etc/google-cloud/
sudo cp service-account-key.json /etc/google-cloud/itsmint-vision.json
sudo chmod 600 /etc/google-cloud/itsmint-vision.json
```

### 2.3 Sicurezza

**IMPORTANTE**: Aggiungi al `.gitignore`:
```
# Google Cloud credentials
storage/credentials/
*.json
service-account*.json
```

## 🧪 3. Test dell'Implementazione

### 3.1 Test Configurazione

```bash
php artisan ocr:test-vision
```

### 3.2 Test con Immagine Specifica

```bash
php artisan ocr:test-vision /path/to/card-image.jpg
```

### 3.3 Test Completo Upload

1. Avvia il server: `php artisan serve`
2. Vai su `/dashboard/card-scan`
3. Carica un'immagine di una carta
4. Verifica i risultati nei log

## 💰 4. Gestione Costi

### 4.1 Monitoraggio

- **Console Google Cloud > Billing > Budget & Alerts**
- Crea alert per spese > $5/mese
- Monitora le metriche API usage

### 4.2 Costi Attesi

| Volume Mensile | Costo |
|----------------|-------|
| < 1.000 richieste | **GRATIS** |
| 1.000 - 5.000 | ~$3-7 |
| 5.000 - 10.000 | ~$7-15 |

### 4.3 Ottimizzazione

- Preprocessa le immagini per ridurle se troppo grandi
- Usa cache per evitare riprocessamenti
- Implementa rate limiting se necessario

## 🔧 5. Troubleshooting

### 5.1 Errori Comuni

**"Google Vision API is not properly configured"**
- Verifica che `GOOGLE_VISION_ENABLED=true`
- Controlla che il file JSON esista e sia leggibile
- Verifica che `GOOGLE_CLOUD_PROJECT_ID` sia corretto

**"Vision API error: PERMISSION_DENIED"**
- Verifica che Vision API sia abilitata nel progetto
- Controlla che il service account abbia i permessi corretti
- Verifica che il project ID sia corretto

**"Failed to read image file"**
- Controlla che il file immagine esista
- Verifica i permessi di lettura
- Assicurati che il formato sia supportato (JPG, PNG, etc.)

### 5.2 Debug

Abilita il logging dettagliato:

```bash
# Nel file .env
OCR_LOG_VISION=true
LOG_LEVEL=debug
```

Poi controlla i log:
```bash
tail -f storage/logs/laravel.log
```

### 5.3 Performance

Se l'OCR è lento:
- Riduci la dimensione delle immagini (max 2000px width)
- Controlla la connessione internet
- Verifica la latenza verso Google Cloud

## 🚀 6. Prossimi Passi

Una volta configurato correttamente:

1. **Test con carte reali** - Prova diverse tipologie di carte
2. **Ottimizza i pattern** - Aggiorna `config/ocr.php` se necessario
3. **Monitor performance** - Tieni d'occhio velocità e precision
4. **Implementa cache** - Per immagini già processate
5. **Backup configuration** - Salva la configurazione funzionante

## 📞 Support

Se hai problemi:
1. Controlla i log di Laravel
2. Verifica la configurazione Google Cloud
3. Testa con il comando `ocr:test-vision`
4. Consulta la [documentazione ufficiale](https://cloud.google.com/vision/docs)

---

✅ **Configurazione completata con successo quando:**
- Il comando `php artisan ocr:test-vision` funziona
- Le immagini caricate tramite web interface vengono processate
- I log mostrano confidence scores > 80% 