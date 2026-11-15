# Segurança, LGPD e acessibilidade — política para o protótipo

- **Dados pessoais mínimos:** nome/e-mail do solicitante, descrição e histórico do chamado; evitar CPF, senhas ou outros dados sensíveis no campo livre. Base legal e finalidade devem ser definidas pela CONFINTER antes de produção.
- **Controle de acesso:** técnicos e administradores somente após autenticação e conferência de papel, inclusive nas rotas de exportação. Testar tentativa de acesso direto e adulteração de sessão.
- **Tokens de consulta:** exibidos apenas ao solicitante; armazenar somente SHA-256, não registrar em analytics, query strings ou logs. TLS obrigatório em ambiente público.
- **API IoT:** negar ingestão quando `IOT_API_KEY` não estiver definida; usar chave fora do repositório, rotacionar, proteger por HTTPS e prever segredo individual por dispositivo em produção.
- **Auditoria:** manter registro de criação, alteração, técnico responsável e eventos IoT; não usar histórico como fonte para editar dados pessoais sem política definida.
- **Retenção:** fixar prazos com a empresa e documentar processo de eliminação/anônimização; não apagar registros indiscriminadamente enquanto necessário para auditoria.
- **Acessibilidade:** testar teclado, foco, alto contraste, ampliação, linguagem clara e redução de movimento em todas as páginas, sobretudo com efeitos 3D. VLibras é recurso complementar, não substitui boas práticas WCAG/eMAG.
- **Riscos pendentes:** scanners SAST, proteção contra abuso/limitação de taxa, IDOR, CSP específica, autenticação granular de dispositivo, revisão de dependências e pentest em ambiente autorizado.
