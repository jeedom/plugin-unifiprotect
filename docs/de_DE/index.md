# UniFi-Protect-Plugin

## Beschreibung

Dieses Plugin verbindet Jeedom über die offizielle Integration API und einen API-Schlüssel mit UniFi Protect. Es erkennt Controller, Kameras und Klingeln, meldet deren Verbindungsstatus und stellt Kamera-Snapshots bereit.

## Plugin-Konfiguration

1. Bei [UniFi Site Manager](https://unifi.ui.com/) anmelden.
2. **Einstellungen → API-Schlüssel** öffnen, einen Schlüssel erstellen und kopieren. Er wird nur einmal angezeigt.
3. Im Plugin die lokale Adresse des Controllers, den HTTPS-Port (normalerweise `443`), den API-Schlüssel und das Aktualisierungsintervall eintragen.
4. Speichern und **UniFi-Protect-Geräte suchen** auswählen.

Der API-Schlüssel ersetzt den bisherigen Benutzernamen und das Passwort vollständig. Wenn das Kamera-Plugin installiert ist, werden Protect-Kameras automatisch darin angelegt.

## Verfügbare Informationen

- Controller: API-Status, ID und `modelKey`;
- Kamera: Verbindung, offizieller Status und JPEG-Snapshot;
- Klingel: Verbindung und offizieller Status.

## Einschränkungen

Die offizielle API liefert derzeit keine detaillierte NVR-Telemetrie, keinen Aufnahmestatus, keine Steuerung des Aufnahmemodus und keinen REST-Ereignisverlauf. Die entsprechenden alten Befehle werden beim Update entfernt.
