# Complemento UniFi Protect

## Descripción

Este complemento conecta Jeedom a UniFi Protect mediante la API de integración oficial y una clave API. Detecta el controlador, las cámaras y los timbres, informa de su conexión y proporciona instantáneas de las cámaras.

## Configuración

1. Inicie sesión en [UniFi Site Manager](https://unifi.ui.com/).
2. Abra **Configuración → Claves API**, cree una clave y cópiela. Solo se muestra una vez.
3. Configure la dirección local del controlador, el puerto HTTPS (normalmente `443`), la clave API y la frecuencia de actualización.
4. Guarde y seleccione **Buscar equipos UniFi Protect**.

La clave API sustituye completamente al usuario y la contraseña anteriores. Si está instalado el complemento Cámara, las cámaras Protect se crean automáticamente en él.

## Información disponible

- Controlador: estado de la API, identificador y `modelKey`;
- Cámara: conexión, estado oficial e instantánea JPEG;
- Timbre: conexión y estado oficial.

## Limitaciones

La API oficial no proporciona actualmente telemetría detallada del NVR, estado de grabación, control del modo de grabación ni historial REST de eventos. Los comandos antiguos correspondientes se eliminan durante la actualización.
