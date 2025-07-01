# ItsMint Alpha Invites System

Sistema completo per gestire l'accesso alpha di ItsMint attraverso codici invito.

## Configurazione

Il sistema alpha è controllato dalla variabile `ALPHA_MODE` nel file `.env`:

```env
ALPHA_MODE=true  # Richiede codici invito per la registrazione
ALPHA_MODE=false # Registrazione libera
```

## Creazione Inviti

### Creare inviti singoli o multipli

```bash
# Creare 1 invito base (scade in 30 giorni)
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites

# Creare 10 inviti per amici
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --count=10 \
  --name="Il Tuo Nome" \
  --inviter-email="tua@email.com" \
  --batch-name="Amici Alpha Test"

# Invito per email specifica
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --email="amico@email.com" \
  --name="Il Tuo Nome" \
  --days=7

# Invito con usi multipli
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --uses=5 \
  --days=60 \
  --batch-name="Famiglia"
```

### Opzioni disponibili
- `--count=N` - Numero di inviti da creare (default: 1, max: 100)
- `--email=EMAIL` - Lega l'invito a una email specifica
- `--days=N` - Giorni di validità (default: 30, 0 = mai scade)
- `--uses=N` - Numero massimo di utilizzi (default: 1)
- `--name=NOME` - Il tuo nome (chi ha creato l'invito)
- `--inviter-email=EMAIL` - La tua email
- `--batch-name=NOME` - Nome del gruppo di inviti

## Gestione Inviti

### Visualizzare tutti gli inviti
```bash
# Lista completa
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites list

# Solo inviti attivi
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites list --status=active

# Solo inviti usati
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites list --status=used

# Limitare risultati
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites list --limit=20
```

### Disabilitare/Riabilitare inviti
```bash
# Disabilitare un invito specifico
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites disable --code=ABC12345

# Riabilitare un invito
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites enable --code=ABC12345
```

### Statistiche complete
```bash
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites stats
```

### Pulizia automatica
```bash
# Disabilita inviti scaduti e esauriti
docker-compose -f develop-compose.yml exec php php artisan itsmint:manage-invites cleanup
```

## Come Funziona

1. **Modalità Alpha**: Quando `ALPHA_MODE=true`, la registrazione richiede un codice invito valido
2. **Codici Unici**: Ogni invito ha un codice di 8 caratteri maiuscolo (es: `ABC12345`)
3. **URL Diretti**: Gli inviti possono essere condivisi tramite URL: `https://tuodominio.com/register?invite=ABC12345`
4. **Tracciamento**: Il sistema traccia chi ha usato ogni invito e quando

## URL di Invito

Puoi condividere gli inviti in questi modi:

```text
# URL diretto (più semplice)
https://tuodominio.com/register?invite=ABC12345

# Solo il codice (l'utente lo inserisce manualmente)
Codice invito: ABC12345
```

## Monitoraggio

### Stati degli inviti:
- 🟢 **Available** - Pronto all'uso
- 🔄 **Partial** - Parzialmente usato (inviti multi-uso)
- ✅ **Used** - Completamente utilizzato
- ⏰ **Expired** - Scaduto
- ❌ **Disabled** - Disabilitato manualmente

### Controlli automatici:
- Verifica scadenza
- Limite utilizzi
- Email vincolate (se specificate)
- Codici duplicati (impossibili)

## Esempi Pratici

### Invitare 5 amici per test di 1 settimana
```bash
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --count=5 \
  --days=7 \
  --name="Il Tuo Nome" \
  --batch-name="Test Amici Settimana 1"
```

### Invito famiglia con usi multipli
```bash
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --uses=10 \
  --days=90 \
  --batch-name="Famiglia" \
  --name="Il Tuo Nome"
```

### Invito specifico per beta tester esperto
```bash
docker-compose -f develop-compose.yml exec php php artisan itsmint:create-invites \
  --email="betatester@email.com" \
  --days=365 \
  --name="Il Tuo Nome" \
  --batch-name="Beta Testers VIP"
```

## Sicurezza

- I codici sono generati casualmente e verificati per unicità
- Gli inviti possono essere legati a email specifiche
- Scadenze automatiche per limitare l'accesso nel tempo
- Possibilità di disabilitare inviti compromessi istantaneamente
- Tracciamento completo dell'utilizzo

## Disattivare il Sistema Alpha

Per passare alla registrazione libera:
1. Modifica `.env`: `ALPHA_MODE=false`
2. Riavvia l'applicazione
3. La registrazione diventerà accessibile a tutti

## Backup dei Dati

Gli inviti sono salvati nella tabella `invitations` del database. Include:
- Codici e metadati
- Storico utilizzi
- Informazioni sui creatori
- Date di scadenza

Assicurati di includere questa tabella nei backup del database. 