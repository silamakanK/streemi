#!/bin/bash
# Installation initiale d'un serveur Ubuntu 24.04 (AWS EC2) pour Streemi. À lancer une seule fois :
#   bash setup-ec2.sh
set -euo pipefail

echo "==> Mise à jour du système"
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y

echo "==> Installation de Docker et Docker Compose"
sudo apt-get install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"

# Les petites instances (1 Go de RAM) manquent de mémoire avec PostgreSQL + PHP : on ajoute 2 Go de swap
if ! swapon --show | grep -q /swapfile; then
    echo "==> Création d'un fichier d'échange (swap) de 2 Go"
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
fi

mkdir -p ~/streemi ~/backups

echo
echo "Serveur prêt. Étapes suivantes :"
echo "  1. Déconnectez-vous puis reconnectez-vous (pour utiliser docker sans sudo)"
echo "  2. Créez ~/streemi/.env.prod à partir de deploy/.env.prod.example"
echo "  3. Poussez sur la branche main : GitHub Actions déploie automatiquement"
