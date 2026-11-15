# Limpeza para publicação

Fonte: `CONFINTER_PI_VI.rar` (preservado intacto). A cópia pública foi preparada sem modificar o projeto original.

Excluídos: documentos acadêmicos históricos, relatórios de auditoria e roteiros internos, resultados de testes anteriores, capturas potencialmente sensíveis, exemplos de cópia, scripts de diagnóstico e utilitários administrativos antigos que não são essenciais para apresentar o código.

**Atenção importante:** scripts de e-mail legados (`send_email/send_email.php`, `php/PHPMailer/send_email.php`) e `php/process - Copia.php` continham valores de autenticação diretamente no código. Foram excluídos da distribuição pública, mas podem ser referenciados por rotas históricas. Revisar eventuais dependências antes de implantar esta cópia.

**Mantidos:** código PHP principal, assets CSS/JS/GLB, migrações SQL V7, scripts operacionais V7, configuração `.env.example`, configuração Docker, testes de código e documentação técnica essencial.

A publicação pública não é uma nova homologação nem prova ausência absoluta de segredos: recomendamos revisão manual final e execução de scanner de segredos antes do `git push`.

Também foram separados o instalador legado que gera um arquivo PHP com credenciais literais e relatórios TXT de auditoria/entrega. Para instalar a V7, prefira as instruções de `README.md` e o script `scripts/migrate_v7.php`.
