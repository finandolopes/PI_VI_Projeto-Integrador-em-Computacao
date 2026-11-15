# CONFINTER PI VI — V7: roteiro de implantação e homologação

**Versão:** V7 candidata à homologação, 2026-11-04. **Base:** V6 preservada. **Stack:** PHP 8.3+, MySQL/MariaDB, Bootstrap/Tailwind legados, CSS compartilhado, JavaScript, Three.js/GLB. **Contexto:** Projeto Integrador VI, ESP32 → API → regras/cooldown → ticket → técnico → resolução/auditoria.

## 1. Auditoria de origem e decisões

O pacote V6 continha (i) tabelas `iot_devices` e `iot_telemetry` definidas em criação dinâmica, fora das migrações SQL distribuídas; (ii) esquema completo com `DROP TABLE` e dados de exemplo, inclusive senha legada; (iii) `.env.example` com chaves duplicadas; (iv) fallback demo permissivo; (v) APIs CRM antigas desativadas por incompatibilidade com o modelo relacional; (vi) endpoint antigo de pagamentos desativado por não corresponder a transações financeiras verificáveis; (vii) módulos visuais com dependências históricas diferentes. A V7 intervém incrementalmente sem retirar a identidade institucional, o painel original ou os GLB.

## 2. Implementado na V7 — mapa técnico

| Área | Implementação | Arquivos principais |
|---|---|---|
| Fundação SQL | baseline sem `DROP`/dados de exemplo; IoT; CRM/auditoria; rascunhos financeiros; jobs/erros; Service Desk; notificações | `sql/v7/001` até `040`, `scripts/migrate_v7.php` |
| Migrações | checksum SHA-256 por versão, lock no MySQL, controle de execuções; rollback auxiliar **apenas de tabelas V7 vazias** | `scripts/migrate_v7.php`, `scripts/rollback_v7_addons.php` |
| Configuração | `.env` sem duplicatas, carga de ambiente, demo explícito apenas em desenvolvimento, timezone configurável | `.env.example`, `php/bootstrap_env.php`, `php/conexao.php` |
| Segurança | matriz de permissões admin/gestor/técnico/comercial/financeiro; adaptações nas rotas; CSRF; senha antiga só por opt-in | `php/access_policy_v7.php`, `php/sd_authorization.php`, `php/seguranca.php`, logins |
| CRM | listagem/detalhe em `clientes`+`requisicoes`; atualização de estágio protegida, transacional e auditável quando ativada | `php/crm_service_v7.php`, `admin/crm/api/` |
| Cliente 360° | solicitações e histórico CRM referenciados por ID do cliente (sem inferir relações por e-mail) | `admin/crm/ficha-360-v7.php` |
| Financeiro | registro de rascunho interno, chave idempotente, sem confirmação falsa de gateway | `php/finance_service_v7.php`, `admin/financeiro/lancamentos-v7.php` |
| Pagamentos | contrato/identificação idempotente documentados; webhook retorna 501 enquanto não houver provedor homologado | `php/payment_contract_v7.php`, `api/payments/webhook_v7.php` |
| Personalização | central por perfil, atalhos, densidade, preferências, pesquisa global por RBAC | `admin/meu-trabalho-v7.php`, `admin/preferencias-v7.php`, `admin/pesquisa-v7.php` |
| Rastreabilidade | eventos V7 + fontes auditáveis anteriores (histórico SD/IoT/CRM) | `admin/historico-operacional-v7.php` |
| Operação | heartbeat e execução do cron registrados, falhas de API agregadas sem payload | `scripts/cron_health_v7.php`, `scripts/cron_notificacoes.php`, `admin/diagnostico-v7.php` |
| Design | catálogo de componentes, CSS V7 com padrão admin responsivo, layout editorial da landing | `admin/catalogo-componentes-v7.php`, `admin/assets/css/confinter-v7.css`, `assets/css/confinter-v7-editorial.css` |
| Performance | seis imagens antes embutidas no HTML tornadas WebP externos para cache | `assets/media/v7/`, `index.php` |
| Testes e continuidade | testes unitários puros; teste integrado destrutivo só no banco `_test`; backup e restauração restrita | `tests/`, `scripts/backup_v7.sh`, `scripts/restore_v7_test_only.sh` |

## 3. Preparação do ambiente

**Obrigatório:** PHP com extensões mysqli, mbstring, json, openssl; MariaDB/MySQL com base separada de homologação; HTTPS no acesso remoto; backups verificados. Recomenda-se PHP 8.3+, MariaDB 11.x e controle de versão. Não copiar `.env` de produção para repositórios.

```bash
cp .env.example .env
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
# Atualize DB_*, APP_SECRET_KEY, IOT_API_KEY, MYSQL_ROOT_PASSWORD e MAIL_*
docker compose -f docker-compose.v7.yml up -d --build
docker compose -f docker-compose.v7.yml exec web php scripts/migrate_v7.php --status
docker compose -f docker-compose.v7.yml exec web php scripts/migrate_v7.php
docker compose -f docker-compose.v7.yml exec web php scripts/create_admin_v7.php 'Administrador' admin admin@seu-dominio.example
```

O Docker Compose V7 é um modelo de ambiente de homologação, **não foi executado neste ambiente de geração**. Nunca publicar banco e `.env` sem revisão de segurança. O instalador pressupõe que o banco vazio já exista, por exemplo criado pelo Compose. Em bancos existentes, backup/ensaio de migração são obrigatórios. A criação de tabelas ausentes `IF NOT EXISTS` não significa que a estrutura de tabelas antigas esteja correta; compare `SHOW CREATE TABLE` com o esquema antes de permitir operações transacionais. Se `clientes`/`requisicoes` ainda forem MyISAM, mantenha `CRM_V7_WRITE_ENABLED=0` até conversão planejada para InnoDB, com backup.

**Dados iniciais:** o esquema seguro não cadastra `admin/admin` ou contas MD5. Use `scripts/create_admin_v7.php`. Senhas MD5/texto plano legadas ficam desabilitadas por padrão. Para contas existentes, utilize redefinição controlada com `scripts/reset_user_password_v7.php usuarios login` (ou `adm login`) no terminal. Não ative `ALLOW_LEGACY_PASSWORDS=1` em produção.

## 4. Segurança e contratos

**Permissões:** cliente/CRM, financeiro, Service Desk, IoT e observabilidade aplicam a matriz `php/access_policy_v7.php`. `require_permissao` legado utiliza a mesma normalização de perfis, sem ampliar automaticamente permissões. Acesso negado deve produzir 403, com dados sensíveis ocultos. A autorização do backend prevalece sobre links do menu.

**CRM:** `admin/crm/api/get-pipeline.php` e `get-lead-detalhes.php` aceitam somente GET de usuário autorizado. `update-lead-stage.php` exige POST, CSRF, transição permitida, tabelas InnoDB e flag explícita. `salvar-lead.php` e `deletar-lead.php` permanecem desabilitadas até mapeamento dos fluxos legados e testes com dados de homologação.

**Financeiro:** o lançamento tem apenas natureza administrativa interna. `finance_entries_v7` não substitui conciliação ou liquidação bancária. Criação exige flag, permissão, CSRF e chave única de idempotência. Sem provedor real, `api/payments/webhook_v7.php` responde 501. Para integrar provedores: assinar e autenticar webhooks, definir IDs externos únicos, registrar recebimento de evento de forma idempotente, verificar estado na API do provedor, reconciliar e testar falhas/duplicidades. Nunca marcar uma requisição de crédito como receita recebida.

**API IoT:** aceita POST com `X-IoT-Key`, limita corpo a 32 KiB e não abre CORS indiscriminado. A persistência e as regras requerem schema instalado. As falhas registram categoria/ID de requisição, nunca a chave ou payload integral. Testar cooldown concorrente em banco de homologação.

## 5. Critérios de aceite e execução de testes

| Verificação | Como executar | Critério |
|---|---|---|
| Lint PHP | `find . -name '*.php' -print0 \| xargs -0 -n1 php -l` | Zero erros |
| Regras de negócio | `php tests/test_v7_unit.php`, `php tests/test_sla.php`, `php tests/test_policies.php`, `php tests/test_notifications_allocation.php` | Todos passam |
| Banco isolado | Criar `confinter_test`, migrar e executar `V7_TEST_ALLOW_DB_MUTATION=yes php tests/v7_db_integration.php` | Cliente→requisição→CRM, rascunho idempotente, ESP32 simulado→regra→ticket→cooldown→técnico→resolução→histórico |
| Acesso e CSRF | Contas admin, gestor, técnico, comercial, financeiro e usuário; POST sem CSRF | 403/419 e nenhuma escrita indevida |
| Jobs persistentes | cron `scripts/cron_notificacoes.php` e `cron_health_v7.php` | Histórico, sem duplicações, alerta resolvido após normalização |
| Mobile visual | Chrome/Firefox/Safari: 360, 390, 768, 1440 px | Sem sobreposição, corte, barra horizontal, alvos pequenos; formulário funcional |
| 3D | Alternar 3 slides, reduzir movimento, WebGL desligado | GLB correto ou fallback acessível; FPS/memória aceitáveis |
| Segurança e privacidade | Revisão CSRF, sessão, uploads, autorização, logs, LGPD | Nenhuma falha crítica aberta |
| Backup e restauração | `scripts/backup_v7.sh`; restauração **somente** `_test` com `CONFIRM_RESTORE=test_only` | Arquivo íntegro, dados e indicadores coerentes |

**Não executar** o teste integrado com banco de produção, pois ele cria e limpa registros. O teste exige por design `DB_NAME` terminando `_test` e `V7_TEST_ALLOW_DB_MUTATION=yes`.

## 6. Operação e alertas

Cron recomendado em servidor com PHP e acesso à base (5 minutos):

```cron
*/5 * * * * cd /var/www/html && /usr/bin/php scripts/cron_notificacoes.php >> /var/log/confinter/cron_alertas.log 2>&1
*/5 * * * * cd /var/www/html && /usr/bin/php scripts/cron_health_v7.php >> /var/log/confinter/cron_health.log 2>&1
```

A página `admin/diagnostico-v7.php` exibe saúde de extensão, banco, tabelas e heartbeat, sem apresentar credenciais. O limite de atraso é `CF_MONITOR_ALERT_SECONDS`. A notificação persistente já existente na V3 é reaproveitada; a V7 adiciona observabilidade dos jobs. Dispositivos são identificados como sem comunicação por horário de telemetria, não apenas pelo status cadastrado.

## 7. Pendências explícitas

1. **Não homologado:** não havia serviço MySQL/MariaDB e extensões PHP `mysqli`/`mbstring` no ambiente de geração, nem Docker disponível. Logo não há evidência de CRUD real, transações ou ESP32 físico executados aqui.
2. **Não homologado:** Chromium headless não inicializou nem em página vazia; faltam capturas desktop/mobile e auditoria visual real de todas as rotas.
3. **Crédito/financeiro:** pagamentos em gateway externo, conciliação e webhooks reais não foram habilitados; exigem provedor contratado e contratos técnicos. Não usar registros de requisição como receita.
4. **CRM legado:** duas APIs de leitura agora usam tabelas reais, atualização de estágio é opt-in; APIs de criação e exclusão continuam deliberadamente fechadas. Conversão de tabelas MyISAM legadas precisa de migração com backup.
5. **Módulos legados:** o CSS compartilhado V4 permanece, mas telas antigas independentes exigem homologação visual e revisão de autorização específica. V7 não prova que todas as dezenas de módulos legados foram homologados.
6. **Migrações/rollback:** tabelas auxiliares V7 podem ser removidas apenas quando vazias. O baseline e o histórico do PI VI nunca são descartados automaticamente, para evitar perda de dados.
7. **Performance:** o HTML da landing foi reduzido, mas ainda faltam medições de FPS, memória e Core Web Vitals em navegadores reais; bibliotecas 3D dependem de CDN nesta base.
8. **Produção:** testes de concorrência, carga, logs, recuperação de falhas, backups restabelecidos e conformidade final LGPD ainda são critérios de bloqueio.

## 8. Próximo trabalho para outra IA/desenvolvedor

Partir **exclusivamente deste projeto V7**, ler este documento e o arquivo `README_V7.md`, comparar esquemas, levantar ambiente Docker/MySQL de homologação, registrar logs/capturas reais e corrigir itens que falharem. Não remodelar a landing ou substituir identidade; não gerar dados fictícios como evidência de homologação. Cada nova correção deve apresentar arquivo alterado, teste realizado, resultado e pendência correspondente.
