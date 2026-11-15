# CONFINTER — Módulos IoT Simulados
## Projeto Integrador — Computação II

---

## Visão Geral

Este projeto implementa **3 módulos IoT simulados via software**, sem necessidade de hardware físico, adequados para apresentação acadêmica. A simulação é justificada pelo padrão da indústria (*digital twin*) e pelo protocolo HTTP como substituto do MQTT em ambientes sem broker físico.

---

## Módulos Implementados

### 🖥️ Módulo 1 — Totem de Atendimento (Sensor de Presença)

**Conceito IoT:** Simula um totem físico com **sensor PIR (Passive Infrared)** que detecta presença de clientes e gerencia a fila de atendimento.

**Arquivos:**
```
totem.php                   ← Tela pública (TV/monitor do escritório)
admin/fila.php              ← Painel do atendente
php/iot_fila_status.php     ← API: estado da fila
php/iot_pegar_senha.php     ← API: cliente retira senha
```

**Como funciona:**
- O totem exibe o número sendo atendido em tempo real
- Cliente clica em "Pegar Senha" → recebe número e tempo estimado
- Admin clica em "Chamar Próximo" → número avança no totem automaticamente
- O movimento do mouse simula o sensor PIR (detecção de presença)
- Polling a cada 4s simula a leitura contínua do sensor via HTTP (substituto do MQTT)

**Justificativa acadêmica:** Em sistemas IoT reais, sensores PIR enviam sinais via MQTT para um broker (ex: Mosquitto). Nesta implementação, o polling HTTP substitui o MQTT sem perda de conceito.

---

### 📊 Módulo 2 — Monitor de SLA em Tempo Real

**Conceito IoT:** Simula **sensores de medição de tempo** em pontos de atendimento que enviam métricas de SLA continuamente para o dashboard.

**Arquivos:**
```
admin/sla.php               ← Dashboard de SLA
admin/sla_status.php        ← API: métricas atuais
```

**Como funciona:**
- Coleta dados reais das requisições do banco (tempo de abertura, conclusão)
- Calcula KPIs: taxa de resposta SLA, taxa de conclusão, média de horas
- Exibe alertas quando requisições ultrapassam 80% do tempo-limite (72h)
- Feed de terminal simula logs de sensores IoT em tempo real
- Polling a cada 10s atualiza o banner de alerta automaticamente

**Métricas monitoradas:**
| KPI | Meta | Critério |
|-----|------|---------|
| 1ª Resposta | ≥ 90% em ≤ 24h | Verde/Amarelo/Vermelho |
| Taxa Conclusão | ≥ 85% em ≤ 72h | Verde/Amarelo/Vermelho |
| Req. em aberto | ≤ 5 simultâneas | Verde/Amarelo/Vermelho |

---

### 🔐 Módulo 3 — Terminal de Biometria / Check-in QR Code

**Conceito IoT:** Simula um **leitor biométrico/RFID** que registra a chegada de clientes via QR code único, como os utilizados em controle de acesso industrial.

**Arquivos:**
```
checkin.php                     ← Terminal público (tablet da recepção)
admin/checkin.php               ← Painel do recepcionista
admin/checkin_poll.php          ← API: novos check-ins (live)
php/iot_checkin_registrar.php   ← API: registrar check-in
php/iot_checkin_status.php      ← API: status do token
```

**Como funciona:**
- Cliente acessa `checkin.php` (tablet na entrada ou link por WhatsApp)
- Preenche nome, CPF e finalidade → sistema gera token único de 8 caracteres
- QR code é gerado localmente com o token + dados
- Admin vê o check-in aparecer **em tempo real** na tela (polling 5s)
- Admin confirma → terminal do cliente atualiza para "✅ Check-in confirmado"
- CPF é mascarado no painel admin por privacidade (LGPD)

**Fluxo de dados:**
```
[Cliente no terminal] → POST /php/iot_checkin_registrar.php
        ↓
   [Banco de dados: iot_checkins]
        ↓
[Admin polling a cada 5s] ← GET /admin/checkin_poll.php
        ↓
[Admin confirma] → POST /admin/checkin.php?acao=confirmar
        ↓
[Terminal cliente polling] ← GET /php/iot_checkin_status.php?token=XXXXXXXX
        ↓
   [✅ Confirmado exibido no terminal]
```

---

## Instalação

### 1. Banco de dados
Execute o script SQL:
```sql
-- Arquivo: sql/iot_tables.sql
```

Se sua tabela `requisicoes` não tiver a coluna `data_conclusao`:
```sql
ALTER TABLE requisicoes ADD COLUMN data_conclusao DATETIME DEFAULT NULL;
```

### 2. Arquivos do servidor
Copie para a raiz do projeto:
```
totem.php
checkin.php
php/iot_fila_status.php
php/iot_pegar_senha.php
php/iot_checkin_registrar.php
php/iot_checkin_status.php
```

Copie para a pasta `admin/`:
```
admin/fila.php
admin/sla.php
admin/checkin.php
admin/checkin_poll.php
admin/sla_status.php
```

### 3. Menu admin (sidebar.php)
Adicione ao menu do painel admin:
```html
<!-- IoT -->
<li class="menu-title"><span>IoT</span></li>
<li>
    <a href="fila.php">
        <i class="fa fa-tower-broadcast"></i>
        <span>Totem de Fila</span>
    </a>
</li>
<li>
    <a href="sla.php">
        <i class="fa fa-gauge-high"></i>
        <span>Monitor SLA</span>
    </a>
</li>
<li>
    <a href="checkin.php">
        <i class="fa fa-fingerprint"></i>
        <span>Check-ins</span>
    </a>
</li>
```

---

## Argumento para a Apresentação

> **"Por que IoT sem hardware físico é válido?"**

O conceito de *Digital Twin* (Gêmeo Digital) é amplamente utilizado na indústria (Siemens, GE, AWS IoT) para simular ambientes físicos em software antes da implantação de hardware. Esta implementação demonstra todos os **padrões arquiteturais IoT**:

1. **Sensores** → substituídos por eventos JS (movimento, tempo, interação)
2. **Protocolo de comunicação** → HTTP polling substitui MQTT sem perda conceitual
3. **Broker/Gateway** → PHP acts como gateway de dados
4. **Dashboard em tempo real** → atualização assíncrona via fetch/polling
5. **Ações (actuators)** → admin confirma/chama → estado muda no dispositivo

---

## Tecnologias Utilizadas

| Camada | Tecnologia |
|--------|-----------|
| Frontend | HTML5, CSS3, JavaScript ES6+ |
| Backend | PHP 8+ |
| Banco de dados | MySQL / MariaDB |
| QR Code | qrcodejs (CDN, sem custo) |
| Gráficos | Chart.js (CDN, sem custo) |
| Ícones | Font Awesome 6 |
| Som simulado | Web Audio API (nativo) |

---

*CONFINTER — Projeto Integrador em Computação II — Turma 006*
