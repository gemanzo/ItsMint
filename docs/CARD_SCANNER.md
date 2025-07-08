# Card Scanner Implementation

## Overview

Sistema completo di riconoscimento automatico di carte tramite OCR (Optical Character Recognition) implementato in Laravel + Vue.js. La funzionalità supporta sia l'utilizzo della camera che l'upload di immagini per il riconoscimento automatico delle carte.

## Features Implementate

### Backend (Laravel)

- **Modello CardScan**: Gestione completa delle scansioni con stati, metadata e relazioni
- **Servizio CardOcrService**: Processamento OCR con Tesseract e preprocessing immagini
- **Controller API**: Endpoint RESTful per upload, gestione e storico scansioni
- **Integrazione Elasticsearch**: Ricerca intelligente per matching delle carte riconosciute
- **Gestione File**: Storage sicuro delle immagini scansite con hash e metadata

### Frontend (Vue.js)

- **CardScanModal**: Componente modale per cattura foto e upload immagini
- **ScanResultsModal**: Visualizzazione risultati OCR con confidence scores
- **Integrazione Camera**: Accesso camera device con supporto mobile (camera posteriore)
- **Interfaccia Responsiva**: UI moderna e user-friendly per desktop e mobile
- **Real-time Feedback**: Indicatori di stato e progress durante il processamento

## Architettura

### Database Schema

```sql
CREATE TABLE card_scans (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    blueprint_id BIGINT REFERENCES blueprints(id) NULLABLE,
    original_filename VARCHAR,
    file_path VARCHAR,
    file_hash VARCHAR INDEXED,
    ocr_text TEXT,
    ocr_data JSON,
    detected_cards JSON,
    confidence_score DECIMAL(5,2),
    status ENUM('pending', 'processing', 'completed', 'failed'),
    error_message TEXT,
    source ENUM('upload', 'camera'),
    metadata JSON,
    processed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### API Endpoints

#### POST /api/card-scans/upload

Carica e processa un'immagine per il riconoscimento OCR

```bash
curl -X POST /api/card-scans/upload \
  -H "Authorization: Bearer {token}" \
  -F "image=@card.jpg" \
  -F "source=camera"
```

#### GET /api/card-scans

Recupera la lista delle scansioni dell'utente

```bash
curl /api/card-scans?status=completed&per_page=10 \
  -H "Authorization: Bearer {token}"
```

#### GET /api/card-scans/{id}

Dettagli di una specifica scansione

```bash
curl /api/card-scans/123 \
  -H "Authorization: Bearer {token}"
```

#### POST /api/card-scans/{id}/retry

Riprova una scansione fallita

```bash
curl -X POST /api/card-scans/123/retry \
  -H "Authorization: Bearer {token}"
```

#### PUT /api/card-scans/{id}/blueprint

Assegna manualmente una carta alla scansione

```bash
curl -X PUT /api/card-scans/123/blueprint \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"blueprint_id": 456}'
```

#### POST /api/card-scans/{id}/create-user-card

Crea una UserCard dalla scansione

```bash
curl -X POST /api/card-scans/123/create-user-card \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "condition": "NM",
    "language": "EN",
    "quantity": 1,
    "purchase_price": 10.50
  }'
```

## Workflow del Riconoscimento

### 1. Cattura/Upload Immagine

- **Camera**: Accesso camera con preferenza per camera posteriore su mobile
- **Upload**: Supporto per file immagine fino a 10MB
- **Validazione**: Controllo formato e dimensioni minime

### 2. Preprocessing Immagine

- **Ridimensionamento**: Max 1200px larghezza per performance
- **Ottimizzazione OCR**: Conversione grayscale, contrast enhancement
- **Pulizia**: Rimozione noise e miglioramento qualità

### 3. OCR Processing

- **Tesseract**: Engine OCR multi-lingua (EN, JP, IT)
- **Configurazione**: Ottimizzata per testo su carte
- **Whitelist**: Caratteri consentiti per ridurre errori

### 4. Card Matching

- **Elasticsearch**: Ricerca fuzzy nel database carte
- **Similarity Score**: Algoritmo Levenshtein per matching
- **Confidence Ranking**: Ordinamento risultati per probabilità

### 5. Auto-Assignment

- **High Confidence**: Auto-assegnazione per confidence >= 80%
- **Manual Review**: Suggerimenti per confidence 50-80%
- **Manual Assignment**: Ricerca manuale per casi difficili

## Configurazione

### Dipendenze Backend

```bash
composer require thiagoalessio/tesseract_ocr
composer require intervention/image
```

### Tesseract Setup

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-eng tesseract-ocr-jpn tesseract-ocr-ita

# macOS
brew install tesseract tesseract-lang

# Docker (se utilizzi container)
RUN apt-get update && apt-get install -y tesseract-ocr tesseract-ocr-eng tesseract-ocr-jpn tesseract-ocr-ita
```

### Frontend Dependencies

```bash
npm install @heroicons/vue
```

## Utilizzo

### Integrazione in AddCardModal

```vue
<template>
  <!-- Pulsante scansione -->
  <button @click="openScanModal">
    <CameraIcon class="h-4 w-4 mr-1" />
    Scan Card
  </button>

  <!-- Componenti modali -->
  <CardScanModal
    :show="showScanModal"
    @close="closeScanModal"
    @scan-complete="handleScanComplete"
  />

  <ScanResultsModal
    :show="showScanResults"
    :scan="currentScan"
    @close="closeScanResults"
    @card-created="handleCardCreated"
  />
</template>

<script setup>
import CardScanModal from '@/Components/CardScanModal.vue';
import ScanResultsModal from '@/Components/ScanResultsModal.vue';

const handleScanComplete = (scan) => {
  // Gestisci risultati scansione
  if (scan.blueprint) {
    selectBlueprint(scan.blueprint);
  }
};
</script>
```

### Standalone Scanner Page

Pagina dedicata disponibile in `resources/js/Pages/CardScan/Index.vue` con:

- Interfaccia completa di scansione
- Storico scansioni precedenti
- Informazioni sulla funzionalità
- Gestione stati e errori

## Performance & Ottimizzazioni

### Backend

- **Async Processing**: OCR in background con queue jobs (opzionale)
- **Image Caching**: Storage ottimizzato con hash per evitare duplicati
- **Database Indexing**: Indici su user_id, status, confidence_score
- **Error Handling**: Retry automatico per fallimenti temporanei

### Frontend

- **Lazy Loading**: Componenti caricati solo quando necessari
- **Image Optimization**: Preprocessing client-side prima dell'upload
- **Responsive Design**: Interfaccia ottimizzata per mobile
- **Progressive Enhancement**: Fallback per browser senza camera API

## Testing

### Backend Tests

```php
// Test OCR service
public function test_card_ocr_service_processes_image()
{
    $service = app(CardOcrService::class);
    $file = UploadedFile::fake()->image('test_card.jpg');

    $scan = $service->processCardImage($file, 1, 'upload');

    $this->assertEquals('completed', $scan->status);
    $this->assertNotNull($scan->ocr_text);
}

// Test API endpoints
public function test_upload_endpoint()
{
    $response = $this->postJson('/api/card-scans/upload', [
        'image' => UploadedFile::fake()->image('card.jpg'),
        'source' => 'camera'
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['success', 'scan']);
}
```

### Frontend Tests

```javascript
// Test camera functionality
describe('CardScanModal', () => {
  it('should access camera when available', async () => {
    // Mock getUserMedia
    navigator.mediaDevices.getUserMedia = jest
      .fn()
      .mockResolvedValue(mockStream);

    const wrapper = mount(CardScanModal);
    await wrapper.vm.startCamera();

    expect(wrapper.vm.cameraActive).toBe(true);
  });
});
```

## Security Considerations

### File Upload Security

- **Validation**: Controllo MIME type e dimensioni
- **Storage**: File memorizzati fuori da webroot
- **Hash**: Prevenzione duplicati e integrity check
- **Cleanup**: Rimozione automatica file temporanei

### API Security

- **Authentication**: Sanctum token per tutte le API
- **Authorization**: Accesso solo alle proprie scansioni
- **Rate Limiting**: Throttling per prevenire abusi
- **Input Validation**: Sanitizzazione completa input

### Privacy

- **Data Retention**: Policy per rimozione scansioni vecchie
- **Encryption**: Storage crittografato per dati sensibili
- **Logs**: No logging di dati utente sensibili

## Troubleshooting

### Errori Comuni

#### Tesseract non installato

```bash
Error: TesseractOCR: executable not found
```

**Soluzione**: Installare Tesseract e verificare PATH

#### Camera non accessibile

```javascript
Error: getUserMedia not supported
```

**Soluzione**: HTTPS obbligatorio per camera API

#### OCR risultati poveri

**Soluzioni**:

- Migliorare qualità immagine
- Aumentare illuminazione
- Usare contrasto maggiore
- Verificare orientamento carta

#### Performance lente

**Soluzioni**:

- Ridurre dimensioni immagine
- Usare queue jobs per OCR
- Ottimizzare preprocessing
- Cache Elasticsearch

## Future Enhancements

### Planned Features

- **AI Enhancement**: Integrazione GPT-4 Vision per recognition avanzato
- **Batch Processing**: Scansione multipla carte contemporaneamente
- **Mobile App**: App nativa per performance migliori
- **Cloud OCR**: Fallback su servizi cloud (Google Vision, AWS Textract)
- **Real-time Processing**: WebSocket per aggiornamenti in tempo reale

### Optimizations

- **Edge Detection**: Riconoscimento automatico bordi carta
- **Perspective Correction**: Correzione automatica angolo foto
- **Multi-language**: Supporto lingue aggiuntive
- **Custom Training**: Modelli OCR specifici per carte TCG
- **Confidence Learning**: ML per migliorare accuracy nel tempo

## Contribuire

### Setup Sviluppo

1. Clone repository
2. Install dependencies: `composer install && npm install`
3. Configure Tesseract
4. Run migrations: `php artisan migrate`
5. Start development: `npm run dev`

### Guidelines

- Seguire PSR-12 per PHP
- Usare TypeScript per Vue components
- Testare funzionalità OCR con immagini reali
- Documentare nuove API endpoints
- Aggiornare questo README per nuove features
