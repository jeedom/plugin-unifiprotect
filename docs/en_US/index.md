# UniFi Protect plugin

## Description

This plugin connects Jeedom to UniFi Protect through the official Integration API and an API key. It discovers the controller, cameras and chimes, reports their connection state, and provides camera snapshots.

## Compatibility

The controller must run a UniFi Protect version exposing the official `/proxy/protect/integration/v1` API. See the [tested equipment list](https://compatibility.jeedom.com/index.php?v=d&p=home&plugin=unifiprotect).

## Plugin configuration

1. Sign in to [UniFi Site Manager](https://unifi.ui.com/).
2. Open **Settings → API Keys**, create a key and copy it. The key is displayed only once.
3. Configure the plugin with:
   - **UniFi Protect controller**: the controller local IP address or hostname and HTTPS port, usually `443`;
   - **UniFi Protect API key**: the key created in UniFi Site Manager;
   - **Refresh rate**: the interval between device-state requests.
4. Save, then click **Find UniFi Protect equipment**.

The API key fully replaces the former username and password. After upgrading the plugin, a key must be configured before synchronization can run again.

If the Camera plugin is installed, Protect cameras are automatically created in it so their snapshots are available.

## Available information

### Controller

- API availability state;
- official id and `modelKey`.

### Camera

- connection state;
- official state (`CONNECTED`, `CONNECTING`, or `DISCONNECTED`);
- high-quality JPEG snapshot.

### Chime

- connection state;
- official state.

## Official API limitations

The official API does not currently provide detailed NVR telemetry, recording state, recording-mode control, or a REST event history. The former commands for these features are therefore removed when the plugin is upgraded.
