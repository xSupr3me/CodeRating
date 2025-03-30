#!/bin/bash

# Script pour générer un certificat SSL auto-signé pour le développement

# Création d'une autorité de certification (CA)
openssl genrsa -out ca.key 4096
openssl req -new -x509 -days 365 -key ca.key -out ca.crt -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=Coursero CA"

# Création d'une clé privée pour le serveur
openssl genrsa -out coursero.key 2048

# Création d'une demande de signature de certificat (CSR)
openssl req -new -key coursero.key -out coursero.csr -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=coursero.local"

# Configuration d'extension pour les noms alternatifs
cat > v3.ext << EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = coursero.local
DNS.2 = www.coursero.local
EOF

# Signature du certificat par la CA
openssl x509 -req -in coursero.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out coursero.crt -days 365 -extfile v3.ext

echo "Certificats SSL générés avec succès"
echo "Pour l'installation, copiez les fichiers vers les emplacements appropriés:"
echo "  - coursero.crt -> /etc/ssl/certs/"
echo "  - coursero.key -> /etc/ssl/private/"
