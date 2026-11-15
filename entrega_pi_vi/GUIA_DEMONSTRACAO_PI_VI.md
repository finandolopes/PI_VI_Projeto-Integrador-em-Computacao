# Demonstração guiada (8–12 minutos sugeridos, não tempo de execução medido)

1. Mostrar a landing page institucional CONFINTER com navegação, 3D e controle para reduzir movimento.
2. Abrir `service-desk.php`, criar um chamado manual e salvar protocolo/código privado.
3. Consultar o chamado com o código e demonstrar falha com segredo errado.
4. Abrir painel técnico com perfil autorizado; observar estado, prioridade e metas de SLA.
5. Mostrar regra de temperatura (`temperatura > 30`) para dispositivo de demonstração.
6. Enviar uma leitura normal (20) e confirmar que não gerou incidente.
7. Enviar leitura crítica (45); demonstrar ticket automático e IDs da leitura/regra/dispositivo.
8. Enviar segunda leitura crítica (46) no intervalo configurado: evento suprimido pelo cooldown.
9. Mudar responsável/status, incluir nota e finalizar ticket; mostrar histórico e exportação autorizada.
10. Expor limites do protótipo e os testes executados com registros. Não apresentar demo simulada como sensor físico se o ESP32 real não foi utilizado.

Observação: o relatório parcial admite sensores experimentais e dispositivos demonstrativos; um cliente HTTP simulando ESP32 testa integração de API, mas não valida hardware/rede física.
