# PI VI — Escopo integral e critérios de aceite

**Documento-fonte:** *Aprimoramento site CONFINTER: Desenvolvimento de um Service Desk Integrado à IoT para Automação e Gestão de Chamados*, relatório parcial 04/10/2026, seções 2.1, 2.2, 2.4 e 2.5. **Escopo delimitado: protótipo aplicado à CONFINTER**, sem exigir integrações de terceiros, cobertura de todos os equipamentos ou implantação comercial em larga escala.

| Objetivo específico | Implementação / local | Aceite observável | Condição atual |
|---|---|---|---|
| Portal web responsivo, abertura, protocolo e consulta | `service-desk.php`, `php/sd_service.php` | Criar ticket e consultá-lo com protocolo e segredo | Código presente, requer MySQL |
| Ciclo de vida: categoria, prioridade, origem, status, técnico e histórico | `sd_tickets`, `sd_ticket_history`, `admin/service-desk.php` | Alterar estado e ver trilha sem alteração retroativa | Código presente, requer teste |
| API de ingestão ESP32/sensores/totens | `api/iot_ingest.php`, `php/iot_service.php` | POST autenticado 200; sem chave 401; campos inválidos 400 | Código presente, requer integração |
| Motor de regras para abrir incidentes automaticamente | `sdEvalIotRules()`, `sd_iot_rules` | Leitura normal não abre ticket; leitura crítica abre | Código presente, requer integração |
| Cooldown por dispositivo e condição | `sd_iot_rule_events`, bloqueio por regra/dispositivo | Leituras repetidas geram um ticket ativo e eventos suprimidos | Código presente, requer teste concorrente |
| Rastreabilidade entre incidente e leitura original | `sd_tickets.device_id,telemetry_id,rule_id` | Consultar correspondência entre os três IDs e o evento | Código presente, requer consulta comprovada |
| Painel com filtros e indicadores por origem, prioridade, status, resposta/resolução | `admin/service-desk.php`, `service-desk-export.php`, `sdSlaSummary()` | Aplicar filtros e validar totais sobre amostra conhecida | Filtros de origem, prioridade e status no painel; demais segmentos e gráficos dependem de homologação |
| Acessibilidade e segurança | menu de acessibilidade; `sd_authorization.php`, token SHA-256, CSRF e API key | Auditoria por teclado/leitor, perfil proibido recebe 403, credenciais seguras | Parcial |
| Integração, usabilidade, desempenho e escalabilidade | testes CLI e roteiro | Executar bateria em DB teste, concorrência, usuários e medição de tempos | Scripts fornecidos; sem execução E2E neste ambiente |
| Viabilidade técnica, comercial e financeira | `VIABILIDADE_PI_VI.md` | Valores reais obtidos e aprovados pela empresa | Modelo e fórmulas, sem preços comprovados |

**Nota metodológica:** somente os resultados de testes realmente executados podem ser descritos como evidência experimental no relatório final. A presença de código e testes propostos não demonstra funcionalidade homologada.
