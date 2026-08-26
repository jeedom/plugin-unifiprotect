# Plugin UniFi Protect

## Description

Ce plugin connecte Jeedom à UniFi Protect au moyen de l’API d’intégration officielle et d’une clé API. Il découvre le contrôleur, les caméras et les carillons, remonte leur état de connexion et fournit les snapshots des caméras.

## Compatibilité

Le contrôleur doit exécuter une version de UniFi Protect proposant l’API officielle `/proxy/protect/integration/v1`. Vous pouvez consulter [la liste des équipements testés](https://compatibility.jeedom.com/index.php?v=d&p=home&plugin=unifiprotect).

## Configuration du plugin

1. Connectez-vous à [UniFi Site Manager](https://unifi.ui.com/).
2. Ouvrez **Paramètres → Clés API**, créez une clé et copiez-la. La clé n’est affichée qu’une fois.
3. Dans la configuration du plugin, renseignez :
   - **Contrôleur UniFi Protect** : adresse IP ou nom d’hôte local du contrôleur et port HTTPS, généralement `443` ;
   - **Clé API UniFi Protect** : clé créée dans UniFi Site Manager ;
   - **Fréquence de rafraîchissement** : intervalle entre les lectures de l’état des équipements.
4. Enregistrez, puis cliquez sur **Rechercher les équipements UniFi Protect**.

La clé API remplace entièrement l’ancien utilisateur et son mot de passe. Après une mise à jour du plugin, il faut obligatoirement renseigner une clé avant de relancer la synchronisation.

Si le plugin Caméra est installé, les caméras Protect sont automatiquement créées dans celui-ci afin de rendre leurs snapshots disponibles.

## Informations disponibles

### Contrôleur

- état d’accès à l’API ;
- identifiant et `modelKey` officiels.

### Caméra

- état de connexion ;
- état officiel (`CONNECTED`, `CONNECTING` ou `DISCONNECTED`) ;
- snapshot JPEG haute qualité.

### Carillon

- état de connexion ;
- état officiel.

## Limites de l’API officielle

L’API officielle ne fournit pas actuellement la télémétrie système détaillée du NVR, l’état d’enregistrement, le mode d’enregistrement ni un historique REST des événements. Les anciennes commandes correspondantes sont donc retirées lors de la mise à jour du plugin.
