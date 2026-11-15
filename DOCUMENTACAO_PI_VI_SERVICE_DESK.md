# CONFINTER — Projeto Integrador VI: manual de implantação, operação e testes

## Escopo e preservação
O pacote acrescenta o Service Desk em arquivos separados. Páginas legadas, CRM, administração, fila, check-in, IoT e formulários institucionais não foram apagados. A experiência 3D já presente na landing page foi conservada e agora direciona a um Service Desk real. O link flutuante oferece acesso direto ao novo portal. A animação CSS não é WebGL; dispensa GPU dedicada e respeita `prefers-reduced-motion`.

## Requisitos do relatório parcial
- Portal e protocolo: `service-desk.php`, com formulário, protocolo aleatório e código privado de consulta.
- Ciclo de vida: `sd_tickets`, ações técnicas em `admin/service-desk.php` e trilha `sd_ticket_history`.
- API ESP32: `api/iot_ingest.php` aproveita `php/iot_service.php` e chama `sdEvalIotRules`.
- Motor de regras: `sd_iot_rules`, painel `admin/service-desk-regras.php`, operadores `gt/gte/lt/lte`.
- Cooldown: bloqueio nomeado no MySQL (`GET_LOCK`), consulta da última geração e checagem de incidente aberto. Leituras suprimidas continuam sendo registradas na telemetria e em `sd_iot_rule_events`.
- Rastreabilidade: `sd_tickets.telemetry_id`, `device_id`, `rule_id`, `sd_iot_rule_events`.
- Painel: totais, origem IoT, pendências, médias de resposta e de resolução em minutos.
- Segurança: chave IoT obrigatória, consultas com token criptograficamente aleatório (hash SHA-256 no banco), sessão e CSRF nas operações de interface, prepared statements, escaping HTML.
- Escalabilidade: tabelas e índices separados para expansão, sem modificar a base do site antigo.

## Instalar localmente
1. PHP 8.1+ com `mysqli`, `mbstring`; MySQL 8.0+; servidor Apache/Nginx com PHP (o servidor estático não executa PHP).
2. Faça backup dos arquivos e do banco antes da migração. Publique o conteúdo de `PI-V-main` no document root existente.
3. Configure variáveis de ambiente para o **processo PHP**: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e **obrigatoriamente** `IOT_API_KEY` (chave longa, exclusiva, não versionada). O `.env.example` é modelo, mas não é carregado automaticamente por `php/conexao.php`.
4. Execute em MySQL o script `sql/pi_vi_service_desk.sql` no mesmo banco do sistema. Mantenha as tabelas legadas. A API IoT existente cria `iot_devices` e `iot_telemetry` no primeiro uso, sujeito a privilégios CREATE.
5. Acesse `service-desk.php` e abra chamado. Guarde protocolo e código de consulta. No painel `admin/service-desk.php`, autentique-se pelo **login legado** e atribua um responsável, mude status e registre nota.
6. Acesse `admin/service-desk-regras.php`, crie regra por métrica (por exemplo, `temperatura > 30`), prioridade alta e cooldown de 900 segundos.
7. Envie telemetria autenticada e verifique o incidente na gestão.

## Demonstração ESP32 / curl
```bash
curl -i -X POST http://localhost/api/iot_ingest.php \
  -H 'Content-Type: application/json' \
  -H 'X-IoT-Key: CHAVE_CONFIGURADA_NO_SERVIDOR' \
  -d '{"device_id":"esp32_lab_01","device_name":"Sensor do laboratório","metric":"temperatura","value":37.5,"unit":"C"}'
```
Respostas bem-sucedidas contêm `data.id` (identificador da leitura) e `incidents` com `rule_id`, `outcome` (`created`/`suppressed`) e `ticket_id`. Envie novamente dentro do cooldown e verifique `suppressed`. Após resolver o ticket, uma nova leitura somente deverá gerar chamado se a janela do cooldown também tiver expirado.

## SLA
O painel exibe **medidas observadas**: tempo até primeira mudança de status e tempo até resolução, em minutos. Não há ainda uma política de metas contratuais por prioridade nem calendário de horário comercial; portanto esses números não devem ser apresentados como cumprimento formal de SLA. Recomenda-se adicionar tabela de metas por prioridade, escalonamentos, avisos e calendário de expediente em evolução futura.

## Segurança e privacidade — avisos
A autenticação do painel é reutilizada do projeto legado e sua autorização por papéis **não foi auditada integralmente**; não exponha a produção a operadores não autorizados antes de revisar perfis e rotas administrativas. O formulário público emprega limite por sessão, que não equivale a proteção global contra bots: habilite rate limiting também no proxy/WAF. O código privado é apresentado uma única vez. O token permite consultar apenas campos não sensíveis; evite expor logs e dados pessoais. Use HTTPS e backups; adote retenção de dados e governança LGPD.

## Checklist de homologação (ambiente com DB)
- [ ] Confirmar criação das quatro tabelas e índices com usuário de banco autorizado.
- [ ] Abrir chamado e verificar formato de protocolo, consulta com código válido e rejeição com código incorreto.
- [ ] Validar CSRF e limite de solicitações.
- [ ] Criar regra de teste e transmitir leitura normal (sem incidente) e crítica (um incidente).
- [ ] Reenviar 2 leituras críticas em intervalo curto: 2 leituras, 1 incidente, eventos `created` e `suppressed`.
- [ ] Testar 2 ingestões simultâneas; validar único chamado para mesma regra/dispositivo.
- [ ] Alterar status; validar usuário, nota, hora da primeira resposta e da resolução no histórico.
- [ ] Revisar compatibilidade do painel legado, login, CRM, formulários, acessibilidade com teclado e navegação móvel.
- [ ] Testar política de backups e restauração.

## Limitações constatadas
O pacote original continha erros sintáticos em alguns arquivos PHP legados e não incluía `php/conexao.php`. Nesta entrega, o conector ausente foi criado via variáveis de ambiente, mas arquivos legados com erro não foram substituídos indiscriminadamente. Não há MySQL de homologação com dados ou navegador integrado, logo não é possível certificar a execução ponta a ponta ou todas as páginas legadas somente pelo lint. Integração física ESP32, desempenho e escalabilidade são cenários de homologação, não resultados medidos aqui.
