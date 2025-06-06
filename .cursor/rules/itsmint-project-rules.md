---
description: Regole specifiche per il progetto ItsMint - Trading Card Game Collection & Price Tracker
globs: 
alwaysApply: true
---

# 🎯 ItsMint Project Rules

Sei un esperto AI coding assistant specializzato nel progetto **ItsMint**, una web application per collezionisti di Trading Card Games con focus su gestione portfolio, tracking prezzi e ricerca avanzata.

## 🏗️ Architettura del Progetto

### Stack Tecnologico
- **Backend**: Laravel 12.0+ con PHP 8.2+
- **Frontend**: Vue.js 3.4+ con Inertia.js 2.0+ e TypeScript
- **Database**: PostgreSQL con Redis per caching
- **Search Engine**: Elasticsearch 8.17+ per ricerca avanzata
- **Queue System**: Laravel Horizon per background jobs
- **Auth**: Laravel Sanctum
- **Styling**: TailwindCSS con @headlessui/vue
- **State Management**: Pinia
- **Build Tool**: Vite con HMR
- **Testing**: Pest PHP
- **Containerization**: Docker con compose.yml

### Entità Principali
- **Blueprint**: Carta del gioco (modello principale)
- **UserCard**: Carta posseduta dall'utente con prezzo acquisto/stimato
- **Portfolio**: Collezione di carte dell'utente
- **CardPrice**: Prezzi di mercato da CardTrader API
- **Expansion**: Set/espansione delle carte
- **Game**: Gioco di riferimento

## 📁 Struttura File Specifica

### Laravel Backend
```
app/
├── Models/              # Eloquent models con relazioni
├── Services/            # Business logic (ElasticsearchService, BlueprintPriceService)
├── Http/Controllers/Api/ # API endpoints REST
├── Http/Requests/       # Form validation requests
├── Observers/           # Model observers per sync ES
├── Policies/            # Authorization policies
├── Jobs/                # Background jobs per API sync
└── Console/Commands/    # Artisan commands personalizzati
```

### Vue.js Frontend
```
resources/js/
├── Components/          # Componenti riutilizzabili (Modal, Forms, etc.)
├── Layouts/             # Layout principali
├── Pages/              # Pagine Inertia.js
│   ├── Dashboard/Blueprints/ # Gestione blueprint
│   ├── Portfolios/      # Gestione portfolio
│   └── Profile/         # Profilo utente
├── stores/              # Pinia stores (portfolioStore.ts)
└── types/               # TypeScript definitions
```

## 🎯 Regole di Sviluppo Specifiche

### PHP/Laravel
- **Strict Types**: Sempre `declare(strict_types=1)` in ogni file PHP
- **Final Classes**: Controller e Service devono essere `final`
- **Type Hints**: Sempre explicit return types e parameter types
- **Services Pattern**: Business logic complessa va nei Services, non nei Controller
- **Job Queues**: API calls a CardTrader sempre tramite jobs in background
- **Elasticsearch**: Sempre fallback a database se ES non disponibile
- **Observer Pattern**: Usa BlueprintObserver per sync automatico con Elasticsearch

### TypeScript/Vue.js
- **Strict Mode**: TypeScript strict enabled
- **Composition API**: Sempre prefer Composition API over Options API
- **Pinia**: State management centralizzato per portfolio e user data
- **Type Safety**: Definire tipi per API responses e props
- **Axios**: Configurato con baseURL '/api', usa path relativi
- **Inertia**: Sempre usare Inertia.js per navigazione, no Vue Router

### Elasticsearch
- **Service Class**: Tutta la logica ES in `ElasticsearchService`
- **Auto-sync**: BlueprintObserver mantiene index aggiornato
- **Fuzzy Search**: Implementare toleranza typo per nomi carte
- **Fallback**: Sempre graceful degradation a database query
- **Batch Operations**: Grandi import tramite batch jobs

## 🔧 Convenzioni di Coding

### 🐳 Docker-First Development
**ItsMint è un'applicazione Docker-native**. Tutti i comandi devono essere eseguiti nei container:

```bash
# ❌ MAI eseguire direttamente sull'host
php artisan migrate
npm run dev
composer install

# ✅ SEMPRE eseguire nei container Docker
docker exec -it php_itsmint php artisan migrate
docker exec -it node_itsmint npm run dev  
docker exec -it php_itsmint composer install
```

**Container Names**:
- `php_itsmint` - Backend Laravel 
- `node_itsmint` - Frontend Vue.js
- `postgres_itsmint` - Database PostgreSQL
- `redis_itsmint` - Cache Redis
- `elasticsearch_itsmint` - Search Engine

### Database & Models
```php
// Sempre relazioni tipizzate
public function userCards(): HasMany 
{
    return $this->hasMany(UserCard::class);
}

// Cast appropriati per tipi
protected $casts = [
    'price_cents' => 'integer',
    'foil' => 'boolean',
    'created_at' => 'datetime',
];
```

### API Endpoints
```php
// Pattern REST standard
GET    /api/blueprints           # Index con search/filter
GET    /api/blueprints/{id}      # Show
POST   /api/portfolios           # Store
PUT    /api/portfolios/{id}      # Update
DELETE /api/portfolios/{id}      # Destroy

// Search dedicato
GET    /api/blueprints/search?q=luffy&size=10
```

### Vue Components
```vue
<script setup lang="ts">
// Import types
import type { Blueprint, Portfolio } from '@/types'
import { ref, computed } from 'vue'
import { usePortfolioStore } from '@/stores/portfolioStore'

// Props tipizzati
interface Props {
  blueprint: Blueprint
  portfolio?: Portfolio
}

const props = defineProps<Props>()
</script>
```

### State Management
```typescript
// Pinia store structure
export const usePortfolioStore = defineStore('portfolio', () => {
  const portfolios = ref<Portfolio[]>([])
  const currentPortfolio = ref<Portfolio | null>(null)
  
  const addCard = async (blueprintId: number, data: AddCardData) => {
    // API call + state update
  }
  
  return { portfolios, currentPortfolio, addCard }
})
```

## 🚀 Performance & Best Practices

### Laravel Performance
- **Eager Loading**: Sempre `with()` per evitare N+1 queries
- **API Resources**: Usare API Resources per response standardizzate
- **Queue Workers**: Laravel Horizon per job monitoring
- **Caching**: Redis per cache API responses CardTrader
- **Database Indexes**: Su colonne search frequent (name, expansion_code)

### Vue.js Performance
- **Lazy Loading**: Route components con dynamic imports
- **Computed Properties**: Per derived state invece di watchers
- **Key Props**: Sempre `:key` su v-for lists
- **Debouncing**: Per search input (300ms)
- **Image Optimization**: Lazy loading per card images

### Elasticsearch Performance
- **Batch Size**: 100-500 documenti per batch import
- **Index Mapping**: Custom analyzers per expansion codes
- **Query Optimization**: Bool queries con should/must
- **Health Monitoring**: Check cluster health prima delle operazioni

## 🔒 Security & Validation

### API Security
```php
// Form Requests sempre per validation
class StoreUserCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'blueprint_id' => 'required|exists:blueprints,id',
            'purchase_price_cents' => 'nullable|integer|min:0',
            'condition' => 'required|in:mint,near_mint,played',
        ];
    }
}
```

### Frontend Validation
```vue
<!-- Validation real-time con composables -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  blueprint_id: null,
  purchase_price_cents: null,
  condition: 'near_mint'
})
</script>
```

## 🧪 Testing Guidelines

### Laravel Testing
```php
// Feature tests per API endpoints
test('can search blueprints via elasticsearch', function () {
    Blueprint::factory()->create(['name' => 'Monkey.D.Luffy']);
    
    $response = $this->getJson('/api/blueprints/search?q=luffy');
    
    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']]]);
});
```

### Vue Testing
- **Component Tests**: Per logica complessa nei components
- **Store Tests**: Per azioni Pinia store
- **E2E Tests**: Per user flows critici (add card, create portfolio)

## 🐛 Error Handling

### Laravel Exceptions
```php
// Custom exceptions per business logic
class CardTraderApiException extends Exception
{
    public static function rateLimitExceeded(): self
    {
        return new self('CardTrader API rate limit exceeded');
    }
}
```

### Frontend Error Handling
```typescript
// Centralized error handling in stores
const handleApiError = (error: any) => {
  if (error.response?.status === 429) {
    // Rate limit
    toast.error('Too many requests, try again later')
  } else {
    toast.error('Something went wrong')
  }
}
```

## 📦 Deployment & Environment

### Docker Architecture
L'applicazione **ItsMint gira completamente in Docker** con container separati:
- **Container PHP**: `php_itsmint` - Laravel backend
- **Container Node**: `node_itsmint` - Vue.js frontend e build tools
- **Container Database**: PostgreSQL
- **Container Cache**: Redis
- **Container Search**: Elasticsearch

### Docker Command Execution
**IMPORTANTE**: Tutti i comandi devono essere eseguiti nei rispettivi container Docker:

```bash
# ⚠️ Comandi Laravel - SEMPRE nel container PHP
docker exec -it php_itsmint php artisan migrate
docker exec -it php_itsmint php artisan queue:work
docker exec -it php_itsmint composer install
docker exec -it php_itsmint php artisan elasticsearch:index-blueprints

# ⚠️ Comandi Node.js - SEMPRE nel container Node
docker exec -it node_itsmint npm install
docker exec -it node_itsmint npm run dev
docker exec -it node_itsmint npm run build
docker exec -it node_itsmint npm run test

# Accesso shell ai container
docker exec -it php_itsmint bash    # Shell PHP container
docker exec -it node_itsmint sh     # Shell Node container
```

### Docker Development Workflow
```bash
# Avvio ambiente completo
docker compose up -d

# Setup iniziale (solo prima volta)
docker exec -it php_itsmint composer install
docker exec -it node_itsmint npm install
docker exec -it php_itsmint php artisan migrate
docker exec -it php_itsmint php artisan storage:link

# Development con hot reload
docker exec -it node_itsmint npm run dev  # Vite HMR
docker exec -it php_itsmint php artisan horizon  # Queue processing

# Stop ambiente
docker compose down
```

### Environment Variables
```env
# CardTrader API
CARDTRADER_API_TOKEN=your_token
CARDTRADER_API_BASE_URL=https://api.cardtrader.com/api/v2

# Elasticsearch
ELASTICSEARCH_HOST=elasticsearch:9200
ELASTICSEARCH_INDEX=blueprints

# Queue
QUEUE_CONNECTION=redis
HORIZON_ENABLED=true

# Card Scanner OCR
TESSERACT_OCR_PATH=/usr/bin/tesseract
CARD_SCANNER_USE_QUEUE=true
CARD_SCANNER_MOCK_OCR=false
```

### Docker Containers Reference
**⚠️ IMPORTANTE**: ItsMint usa Docker - eseguire sempre i comandi nei container appropriati:

```bash
# Laravel/PHP commands
docker exec -it php_itsmint php artisan migrate
docker exec -it php_itsmint php artisan make:model CardScan
docker exec -it php_itsmint composer require package/name

# Node.js/Frontend commands  
docker exec -it node_itsmint npm install @heroicons/vue
docker exec -it node_itsmint npm run build
docker exec -it node_itsmint npm run dev

# Database operations
docker exec -it php_itsmint php artisan migrate:fresh --seed
docker exec -it php_itsmint php artisan db:seed
```

## 🎨 UI/UX Guidelines

### TailwindCSS Patterns
```vue
<!-- Card component pattern -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow">
  <img class="w-full h-48 object-cover rounded-t-lg" :src="blueprint.image_url" />
  <div class="p-4">
    <h3 class="font-semibold text-gray-900 dark:text-white">{{ blueprint.name }}</h3>
  </div>
</div>
```

### Component Patterns
- **Modal Pattern**: Usa `<Modal>` component con `v-model:show`
- **Form Pattern**: Inertia forms con real-time validation
- **Loading States**: Skeleton loaders per better UX
- **Error States**: Inline error messages con `<InputError>`

## 🔄 API Integration

### CardTrader API
```php
// Job pattern per API calls
class UpdateBlueprintPricesJob implements ShouldQueue
{
    use Queueable;
    
    public function handle(BlueprintPriceService $service): void
    {
        $service->updatePricesFromCardTrader($this->blueprintId);
    }
}
```

### Rate Limiting
- **API Calls**: Throttle con Redis
- **Batch Processing**: 100 requests/minute max
- **Exponential Backoff**: Per retry logic

Queste regole sono specificamente calibrate per il progetto ItsMint e aiuteranno l'AI a generare codice coerente con l'architettura esistente e le best practices del dominio TCG. 