# Checklist de publicação — CONFINTER PI VI

1. Antes de subir, revise se alguma credencial usada no legado ainda está válida. **Troque/rotacione senhas e tokens que já foram embutidos em código legado.**
2. Omitimos dois scripts antigos de e-mail e uma cópia de processador com credenciais literais. Revise referências a eles antes de usar a distribuição pública em produção.
3. Não inclua o RAR original, `.env`, exportações de clientes, capturas com protocolos reais, histórico de auditorias internas ou documentos acadêmicos em repositório público.
4. As migrações `sql/v7/` foram mantidas. O arquivo SQL legado destrutivo foi excluído.
5. As capturas do README já existiam no pacote e não demonstram homologação em produção. Use capturas atuais anonimizadas caso deseje comprovar homologação.
6. Depois da revisão, execute `git init`, `git add .`, `git status` e confirme os arquivos listados antes do primeiro commit.

Para credenciais ou dados publicados anteriormente, excluir arquivos de commits futuros não é suficiente: é necessário rotacionar segredos e tratar também o histórico Git.
