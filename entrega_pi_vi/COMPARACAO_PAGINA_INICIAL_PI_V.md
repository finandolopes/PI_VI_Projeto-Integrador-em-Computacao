# Página inicial: comparação com o PI V

Revisão realizada em 15/11/2026 a partir do `index.php` do [PI V](https://github.com/finandolopes/PI-V/blob/main/index.php), consultado no ramo `main` (blob `67fcea75e973ccde4221031d4374a5053231a2af`).

## Referência e decisões

O PI V apresenta a CONFINTER como consultoria financeira e correspondente bancário. Sua navegação reúne apresentação institucional, valores, serviços, requisição de crédito, dúvidas, depoimentos, contato e login. O destaque inicial utiliza fotografias e legendas provenientes do banco de dados.

A página do PI VI havia acumulado estilos de diferentes revisões, cenas WebGL, cartões em perspectiva e efeitos de movimento. Parte do conteúdo explicava a interface 3D em vez dos serviços. Os acessos ao formulário clássico e ao login apontavam para `institucional-original.php`, ausente nesta distribuição.

| Área | Ajuste local no PI VI |
|---|---|
| Destaque inicial | Fotografia institucional existente, texto sobre os serviços e chamada para análise de crédito |
| Service Desk | Bloco próprio para abrir ou consultar chamados, visível no destaque inicial |
| Efeitos | Retirada do carregamento de Three.js, GLTFLoader e cenas 3D na página principal; arquivos e modelos preservados no projeto |
| Apresentação institucional | Texto sobre a atuação e o atendimento da empresa, com remoção das descrições de efeitos visuais |
| Integração IoT | Mantida a explicação interativa das cinco etapas, sem caracterizá-la como telemetria ao vivo |
| Contato | Canais existentes de WhatsApp e e-mail no lugar do mapa ilustrativo |
| Login | Formulário com token CSRF, usuário e senha, direcionado ao processamento existente |
| Navegação | Conferência dos destinos internos e correção de identificadores repetidos |

A referência anterior foi utilizada para conteúdo e prioridades de navegação. Seu código PHP não foi transplantado: a versão atual preserva a configuração de ambiente e os serviços do PI VI. A alteração não acrescenta comprovação de aprovação de crédito, integração física ou homologação.

## Verificações desta revisão

- Sintaxe do `index.php` verificada com PHP 8.3.14.
- Sintaxe dos dois scripts inline verificada com `node --check`.
- Resposta HTTP 200 para a página, a nova folha de estilos e a fotografia do destaque.
- Renderização PHP em modo de desenvolvimento, sem banco, com presença do campo CSRF no HTML.
- Destinos das âncoras internas existentes e identificadores HTML sem duplicação.

**Validação visual pendente:** o navegador de revisão estava indisponível nesta sessão. O layout em desktop e celular, o menu por toque e a acessibilidade ainda precisam de inspeção em navegador. Não foram enviados formulários, executada autenticação ou realizados testes com banco de dados nesta revisão.
