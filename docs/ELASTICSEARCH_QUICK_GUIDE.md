# 🚀 Elasticsearch Quick Reference Guide

## 🔧 Essential Commands

### Initial Setup
```bash
# 1. Install dependencies (if not done)
composer require elasticsearch/elasticsearch:^8.0

# 2. Start Elasticsearch
docker compose up elasticsearch -d

# 3. Create index and import all blueprints
php artisan elasticsearch:index-blueprints --fresh
```

### Daily Operations
```bash
# Re-index all blueprints (full refresh)
php artisan elasticsearch:index-blueprints --fresh

# Check Elasticsearch status
curl localhost:9200/_cluster/health

# Check index statistics
curl localhost:9200/blueprints/_stats

# Count documents
curl localhost:9200/blueprints/_count
```

### Testing Search
```bash
# Test API endpoint
curl "/api/blueprints/search?q=luffy+op01&size=5"

# Test in Laravel Tinker
php artisan tinker
>>> app(\App\Services\ElasticsearchService::class)->searchBlueprints('luffy', 5)
```

---

## 🔍 Search Examples

| Input | Finds | Type |
|-------|--------|------|
| `"luffy"` | All Luffy cards | Name search |
| `"op-01"` | OP-01 expansion | Exact expansion |
| `"op01"` | OP-01 expansion | Normalized |
| `"OP01"` | OP-01 expansion | Case insensitive |
| `"luffy op01"` | Luffy from OP-01 | Combined search |
| `"sangi"` | Sanji cards | Fuzzy matching |

---

## 🛡️ Troubleshooting

### ES Not Working?
```bash
# 1. Check if running
docker compose ps elasticsearch

# 2. Restart if needed
docker compose restart elasticsearch

# 3. Check logs
docker compose logs elasticsearch -f

# 4. Re-create index
php artisan elasticsearch:index-blueprints --fresh
```

### Search Returns Nothing?
```bash
# Check index exists and has data
curl localhost:9200/blueprints/_count

# If 0 documents, re-index
php artisan elasticsearch:index-blueprints --fresh
```

### Frontend 404 Errors?
- Check axios calls use `blueprints/search` not `/api/blueprints/search`
- Axios already has `baseURL: "/api"` configured

---

## 📊 Performance Monitoring

### Key Metrics
- **Response Time**: Should be < 20ms
- **Index Size**: ~15MB for 4286 blueprints  
- **Memory Usage**: ~50MB RAM
- **Success Rate**: 100% indexing

### Health Checks
```bash
# Application health
curl /api/blueprints/search/stats

# Elasticsearch health  
curl localhost:9200/_cluster/health

# Index health
curl localhost:9200/blueprints/_stats?pretty
```

---

## 🔄 Auto-Sync

**Automatic Updates**: Blueprints are auto-indexed when:
- ✅ New blueprints created
- ✅ Existing blueprints updated  
- ✅ Blueprints deleted
- ✅ Price updates from CardTrader

**Manual Sync**: If data seems out of sync:
```bash
php artisan elasticsearch:index-blueprints
```

---

## 🎯 Smart Search Features

### Expansion Code Intelligence
- `op-01` = `op01` = `OP-01` = `OP01`
- `st-03` = `st03` = `ST-03` = `ST03`

### Fuzzy Matching
- `lufy` → finds "Luffy"
- `sangi` → finds "Sanji"  
- `zoro` → finds "Zoro"

### Multi-Field Search
- `"luffy op01"` → searches name + expansion
- Automatic relevance scoring
- Best matches appear first

---

*Need help? Check the full documentation in `docs/ELASTICSEARCH.md`* 