# Déploiement sur VPS avec nginx reverse proxy

## Architecture finale

```
[Caméra RTSP] (réseau local)
        ↓
    [OBS Studio] (votre PC)
        ↓ RTMP:1935
[VPS Docker nginx-rtmp]
        ↓ HLS (localhost:8080)
        ↓ WebSocket (localhost:8765)
[nginx host reverse proxy]
        ↓ HTTPS:443
[birds.frvaillant.fr]
```

---

## Prérequis VPS

- Ubuntu/Debian (ou compatible)
- ✅ Docker et Docker Compose déjà installés
- ✅ nginx déjà installé et opérationnel
- ✅ certbot déjà installé
- Nom de domaine `birds.frvaillant.fr` pointant vers l'IP du VPS
- 2 vCores, 8 Go RAM minimum

---

## 1. Préparation du VPS

### 1.1 Configurer le DNS

Chez votre registrar DNS, ajouter un enregistrement A :
```
birds.frvaillant.fr  →  IP_DU_VPS
```

Vérifier la propagation :
```bash
dig birds.frvaillant.fr
```

### 1.2 Vérifier les dépendances

```bash
# Vérifier que tout est déjà installé et opérationnel
docker --version
docker-compose --version
sudo systemctl status nginx
certbot --version
```

**Note :** Python n'est pas nécessaire sur le VPS, tout tourne dans des containers Docker.

---

## 2. Déployer l'application Docker

### 2.1 Préparer le projet

```bash
# Créer le répertoire
mkdir -p ~/bird-detection
cd ~/bird-detection

# Transférer les fichiers depuis votre machine locale
# Option 1 : avec scp
scp -r /chemin/vers/obs/* user@vps-ip:~/bird-detection/

# Option 2 : avec git (si le projet est sur GitHub)
git clone votre-repo.git .
```

### 2.2 Créer le fichier .env

```bash
cd ~/bird-detection
nano .env
```

Contenu :
```
ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxxx
```

### 2.3 Vérifier les fichiers

```bash
ls -la
# Vous devez avoir :
# - docker-compose.vps.yml
# - Dockerfile
# - Dockerfile.bird-detector
# - nginx.conf (config nginx Docker)
# - bird_detector.py
# - requirements.txt
# - index.html
# - .env
```

### 2.4 Lancer les containers Docker

```bash
# Construire et lancer
docker-compose -f docker-compose-vps.yml up -d

# Vérifier que tout tourne
docker-compose -f docker-compose-vps.yml ps

# Voir les logs
docker-compose -f docker-compose-vps.yml logs -f
```

### 2.5 Tester en local

```bash
# Test HLS
curl http://localhost:8080/

# Test WebSocket (devrait attendre une connexion)
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" http://localhost:8765
```

---

## 3. Configurer nginx reverse proxy

### 3.1 Copier la configuration nginx

```bash
# Copier le fichier de config
sudo cp nginx-vps-config/birds.frvaillant.fr.conf /etc/nginx/sites-available/

# Créer le lien symbolique
sudo ln -s /etc/nginx/sites-available/birds.frvaillant.fr.conf /etc/nginx/sites-enabled/

# Tester la configuration
sudo nginx -t
```

### 3.2 Obtenir le certificat SSL avec Let's Encrypt

```bash
# Arrêter nginx temporairement
sudo systemctl stop nginx

# Obtenir le certificat
sudo certbot certonly --standalone -d birds.frvaillant.fr

# Relancer nginx
sudo systemctl start nginx

# Ou laisser certbot configurer automatiquement
sudo certbot --nginx -d birds.frvaillant.fr
```

### 3.3 Appliquer la configuration

```bash
# Recharger nginx
sudo systemctl reload nginx

# Vérifier le status
sudo systemctl status nginx
```

### 3.4 Tester l'accès

Ouvrir dans le navigateur : `https://birds.frvaillant.fr`

Vous devriez voir la page mais pas encore de vidéo (normal, OBS n'est pas encore configuré).

---

## 4. Configurer OBS Studio

### 4.1 Ajouter la source caméra RTSP

1. Sources → **+** → **Source Média**
2. Configuration :
   - **URL** : `rtsp://IP_CAMERA:554/stream`
   - Cocher **"Relire en boucle"**
   - Décocher **"Utiliser le tampon réseau"** (pour réduire latence)

### 4.2 Configurer le streaming RTMP

1. **Paramètres** → **Stream**
   - Service : **Personnalisé**
   - Serveur : `rtmp://IP_DU_VPS:1935/live`
   - Clé de stream : `camera`

2. **Paramètres** → **Sortie**
   - Mode : **Avancé**
   - Onglet **Streaming** :
     - Encodeur : **x264** (ou NVENC si GPU disponible)
     - Débit : **2500 Kbps**
     - Intervalle d'images clés : **2 secondes**
     - Préréglage CPU : **veryfast**
     - Profil : **baseline**
     - Tune : **zerolatency**

3. **Paramètres** → **Vidéo**
   - Résolution de sortie : **1920x1080** (ou 1280x720 si upload limité)
   - FPS : **25** ou **30**

### 4.3 Démarrer le stream

1. Cliquer sur **"Démarrer le stream"**
2. Vérifier dans OBS que "Streaming - Actif" apparaît en vert

---

## 5. Vérifications finales

### 5.1 Vérifier la réception du flux sur le VPS

```bash
cd ~/bird-detection

# Logs nginx-rtmp (devrait montrer "Publishing stream 'camera'")
docker-compose -f docker-compose.vps.yml logs -f nginx-rtmp

# Vérifier les fichiers HLS générés
ls -lah ./hls/camera/
# Devrait contenir index.m3u8 et des fichiers .ts
```

### 5.2 Tester l'interface web

1. Ouvrir : `https://birds.frvaillant.fr`
2. Le flux vidéo doit s'afficher
3. Cliquer sur **"Analyser"**
4. Vérifier que l'analyse fonctionne

### 5.3 Vérifier les logs du détecteur

```bash
docker-compose -f docker-compose.vps.yml logs -f bird-detector
```

---

## 6. Firewall et sécurité

### 6.1 Configurer le firewall (ufw)

```bash
# Activer ufw si pas déjà fait
sudo ufw enable

# Autoriser SSH (important !)
sudo ufw allow 22/tcp

# Autoriser HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Autoriser RTMP pour OBS
sudo ufw allow 1935/tcp

# Vérifier les règles
sudo ufw status
```

### 6.2 Limiter l'accès RTMP (optionnel)

Si vous voulez limiter l'accès RTMP à votre IP publique uniquement :

```bash
# Supprimer la règle générale
sudo ufw delete allow 1935/tcp

# Autoriser uniquement votre IP
sudo ufw allow from VOTRE_IP_PUBLIQUE to any port 1935 proto tcp
```

---

## 7. Maintenance

### Voir les logs

```bash
# Logs Docker
docker-compose -f docker-compose.vps.yml logs -f

# Logs nginx host
sudo tail -f /var/log/nginx/birds.access.log
sudo tail -f /var/log/nginx/birds.error.log
```

### Redémarrer les services

```bash
# Redémarrer les containers Docker
docker-compose -f docker-compose.vps.yml restart

# Redémarrer nginx host
sudo systemctl restart nginx
```

### Mettre à jour l'application

```bash
cd ~/bird-detection

# Récupérer les dernières modifications
git pull  # si vous utilisez git

# Reconstruire et relancer
docker-compose -f docker-compose.vps.yml down
docker-compose -f docker-compose.vps.yml up -d --build
```

### Renouveler le certificat SSL

```bash
# Certbot renouvelle automatiquement, mais pour forcer :
sudo certbot renew --dry-run  # test
sudo certbot renew            # réel

# Recharger nginx après renouvellement
sudo systemctl reload nginx
```

### Nettoyer l'espace disque

```bash
# Nettoyer les images Docker inutilisées
docker system prune -a

# Nettoyer les captures anciennes (si besoin)
cd ~/bird-detection
rm -rf ./captures/*
```

---

## 8. Monitoring

### Ressources Docker

```bash
# Voir l'utilisation en temps réel
docker stats
```

### Ressources système

```bash
# Installation de htop
sudo apt install htop

# Voir l'utilisation
htop

# Espace disque
df -h
```

---

## 9. Dépannage

### Le stream OBS ne se connecte pas

1. Vérifier que le port 1935 est ouvert :
   ```bash
   sudo ufw status | grep 1935
   sudo netstat -tlnp | grep 1935
   ```

2. Vérifier les logs Docker :
   ```bash
   docker-compose -f docker-compose.vps.yml logs nginx-rtmp
   ```

3. Tester la connexion depuis votre PC :
   ```bash
   telnet IP_VPS 1935
   ```

### Le site ne s'affiche pas (HTTPS)

1. Vérifier que nginx tourne :
   ```bash
   sudo systemctl status nginx
   ```

2. Tester la config nginx :
   ```bash
   sudo nginx -t
   ```

3. Vérifier le certificat SSL :
   ```bash
   sudo certbot certificates
   ```

### Le flux vidéo ne s'affiche pas sur le site

1. Vérifier que les fichiers HLS sont générés :
   ```bash
   ls -lah ~/bird-detection/hls/camera/
   ```

2. Tester l'accès HLS en local :
   ```bash
   curl http://localhost:8080/live/camera/index.m3u8
   ```

3. Vérifier les logs nginx host :
   ```bash
   sudo tail -f /var/log/nginx/birds.error.log
   ```

### La détection ne fonctionne pas

1. Vérifier la clé API :
   ```bash
   cat ~/bird-detection/.env
   ```

2. Vérifier les logs :
   ```bash
   docker-compose -f docker-compose.vps.yml logs bird-detector
   ```

3. Tester la connexion WebSocket :
   - Ouvrir la console du navigateur (F12)
   - Vérifier les erreurs WebSocket

---

## 10. Optimisations possibles

### Si la latence est trop élevée

1. **Dans OBS** :
   - Réduire le "Intervalle d'images clés" à 1 seconde
   - Utiliser l'encodeur hardware (NVENC/QuickSync)
   - Réduire le débit vidéo

2. **Dans nginx.conf Docker** :
   - Réduire `hls_fragment` à 1s
   - Réduire `hls_playlist_length` à 3s

### Si le VPS est surchargé

1. Réduire la résolution du stream OBS (720p)
2. Réduire le framerate (20 fps)
3. Baisser le débit OBS à 1500 Kbps

---

## Résumé des URLs

- **Site web** : https://birds.frvaillant.fr
- **Stream RTMP (OBS)** : rtmp://IP_VPS:1935/live/camera
- **HLS (interne)** : http://localhost:8080/live/camera/index.m3u8
- **WebSocket (interne)** : ws://localhost:8765

---

## Support

En cas de problème, vérifier dans l'ordre :
1. Les logs Docker : `docker-compose logs`
2. Les logs nginx : `/var/log/nginx/birds.error.log`
3. La console navigateur (F12)
4. OBS : Paramètres → Sortie → Afficher les statistiques
