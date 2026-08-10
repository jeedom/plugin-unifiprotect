# Plugin UniFi Protect

## Descrição

Este plugin liga o Jeedom ao UniFi Protect através da API de integração oficial e de uma chave API. Descobre o controlador, as câmaras e as campainhas, apresenta o estado da ligação e fornece imagens das câmaras.

## Configuração

1. Inicie sessão no [UniFi Site Manager](https://unifi.ui.com/).
2. Abra **Definições → Chaves API**, crie uma chave e copie-a. A chave é apresentada apenas uma vez.
3. Configure o endereço local do controlador, a porta HTTPS (normalmente `443`), a chave API e a frequência de atualização.
4. Guarde e selecione **Procurar equipamentos UniFi Protect**.

A chave API substitui completamente o utilizador e a palavra-passe anteriores. Se o plugin Câmara estiver instalado, as câmaras Protect são criadas automaticamente nesse plugin.

## Informações disponíveis

- Controlador: estado da API, identificador e `modelKey`;
- Câmara: ligação, estado oficial e imagem JPEG;
- Campainha: ligação e estado oficial.

## Limitações

A API oficial não fornece atualmente telemetria detalhada do NVR, estado de gravação, controlo do modo de gravação ou histórico REST de eventos. Os comandos antigos correspondentes são removidos durante a atualização.
