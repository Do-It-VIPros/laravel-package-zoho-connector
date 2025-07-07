# 🧪 Guide de Test - Zoho Connector Package

## 🎯 **Situation actuelle et solutions**

Vous utilisez uniquement l'**API Zoho Creator** sans base de données locale. Voici les options pour tester :

---

## 📋 **Options de test disponibles**

### **Option 1 : Tests Mocked (RECOMMANDÉE) - Sans DB, Sans API**
✅ **Avantages** : Rapide, fiable, pas de dépendances  
✅ **Usage** : Tests unitaires et de régression  

```bash
# Tests avec mocks complets
./vendor/bin/pest tests/Unit --colors

# Tests API mocked
./vendor/bin/pest tests/Feature/SimpleApiTest.php --colors
```

### **Option 2 : Tests avec API Zoho de Test - Sans DB**
✅ **Avantages** : Validation réelle de l'API  
⚠️ **Limites** : Dépend de la connectivité et des quotas  

```bash
# Configuration requise dans .env.testing
ZOHO_REAL_API_TESTS=true
ZOHO_CLIENT_ID=your_test_client_id
ZOHO_CLIENT_SECRET=your_test_secret

# Lancement des tests
./vendor/bin/pest --group=real-api
```

### **Option 3 : Tests avec Base de Données - Complet**
✅ **Avantages** : Tests complets des models  
⚠️ **Limites** : Nécessite setup DB  

---

## ⚙️ **Configuration recommandée pour VOTRE cas**

### **1. Créer le fichier de configuration**

```bash
# Copier le template
cp .env.testing.example .env.testing

# Éditer avec vos credentials de test Zoho
nano .env.testing
```

### **2. Remplir avec vos credentials de TEST Zoho**

```env
# .env.testing
ZOHO_TEST_MODE=true
ZOHO_CLIENT_ID=1000.xxxxxxxxx  # Votre Client ID de test
ZOHO_CLIENT_SECRET=xxxxx       # Votre Client Secret de test
ZOHO_USER=test@yourcompany.com # Votre utilisateur de test
ZOHO_APP_NAME=your_test_app    # Votre app de test

# Tests sans DB (recommandé pour vous)
ZOHO_USE_DATABASE_TESTS=false
ZOHO_REAL_API_TESTS=true      # Si vous voulez tester la vraie API
```

### **3. Lancer les tests adaptés**

```bash
# Tests unitaires (mocked, rapides)
./vendor/bin/pest tests/Unit/Services --colors

# Tests API avec mocks (pas de DB requise)
./vendor/bin/pest tests/Feature/SimpleApiTest.php --colors

# Tests avec vraie API (si configuré)
ZOHO_REAL_API_TESTS=true ./vendor/bin/pest tests/Feature/SimpleApiTest.php --colors
```

---

## 🚀 **Tests recommandés pour VOTRE contexte**

### **Tests de base (sans configuration)**
```bash
# Tests des helpers et utilities
./vendor/bin/pest tests/Unit/Foundation --colors

# Tests des services avec mocks
./vendor/bin/pest tests/Unit/Services/ZohoCreatorServiceTest.php --colors
```

### **Tests API avec vos credentials (recommandé)**
```bash
# Une fois le .env.testing configuré
./vendor/bin/pest tests/Feature/SimpleApiTest.php --colors
```

### **Tests d'intégration (optionnel)**
```bash
# Si vous voulez tester contre votre vraie API de test
ZOHO_REAL_API_TESTS=true ./vendor/bin/pest --group=integration
```

---

## 📊 **Stratégie de test optimale pour vous**

### **Phase 1 : Tests Mocked (Immédiat)**
- ✅ Validation de la logique métier
- ✅ Tests de regression
- ✅ Pas de setup requis

### **Phase 2 : Tests API de Test (Recommandé)**
- ✅ Validation avec votre environnement Zoho de test
- ✅ Tests end-to-end réalistes
- ✅ Détection des changements API

### **Phase 3 : Tests de Production (Optionnel)**
- ✅ Tests smoke en production
- ✅ Monitoring de la santé de l'API

---

## 🔧 **Commandes utiles**

### **Tests rapides (développement)**
```bash
# Tests essentiels uniquement
./vendor/bin/pest tests/Unit/Foundation --colors

# Tests d'un service spécifique
./vendor/bin/pest tests/Unit/Services/ZohoCreatorServiceTest.php --colors
```

### **Tests complets (avant release)**
```bash
# Tous les tests unitaires
./vendor/bin/pest tests/Unit --colors

# Tests avec vraie API (si configuré)
ZOHO_REAL_API_TESTS=true ./vendor/bin/pest tests/Feature --colors
```

### **Debugging des tests**
```bash
# Tests avec output détaillé
./vendor/bin/pest tests/Feature/SimpleApiTest.php --colors -v

# Tests avec coverage
./vendor/bin/pest tests/Unit --coverage --colors
```

---

## ❓ **Questions fréquentes**

### **Q: Faut-il absolument une base de données ?**
**R:** Non ! Les tests essentiels fonctionnent avec des mocks. La DB n'est utile que pour tester les models Eloquent.

### **Q: Comment tester sans impacter mes quotas API ?**
**R:** Utilisez les tests mocked pour le développement, et les tests API réels seulement avant les releases.

### **Q: Mes tests échouent avec "Service not ready" ?**
**R:** Le service cherche des tokens en DB. Soit configurez `.env.testing`, soit utilisez les tests mocked dans `SimpleApiTest.php`.

### **Q: Comment créer des tests pour mon cas d'usage spécifique ?**
**R:** Copiez `SimpleApiTest.php` et adaptez les mocks à vos reports et données.

---

## 📞 **Prochaines étapes**

1. **Créez votre `.env.testing`** avec vos credentials de test
2. **Lancez** `./vendor/bin/pest tests/Feature/SimpleApiTest.php`
3. **Adaptez** les tests à vos reports spécifiques
4. **Intégrez** dans votre CI/CD

Le package est prêt à être testé selon vos besoins ! 🎉