-- +statement
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `usuario` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `senha` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `perfil` enum('usuario','admin') NOT NULL DEFAULT 'usuario',
  `img_perfil` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `telefone` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `data_cadastro` timestamp DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acesso` timestamp NULL DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `adm` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` varchar(20) DEFAULT 'admin',
  `data_cadastro` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `rg` varchar(20) DEFAULT NULL,
  `endereco` text,
  `bairro` varchar(50) DEFAULT NULL,
  `cidade` varchar(50) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `data_cadastro` timestamp DEFAULT CURRENT_TIMESTAMP,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `contador_visitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_visita` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tempo` time DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `pagina` varchar(255) DEFAULT NULL,
  `sessao_id` varchar(255) DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `pais` varchar(50) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `navegador` varchar(100) DEFAULT NULL,
  `dispositivo` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_visita` (`data_visita`),
  KEY `ip_address` (`ip_address`),
  KEY `sessao_id` (`sessao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `depoimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_cliente` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mensagem` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status_mod` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'pendente',
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `aprovado` tinyint(1) DEFAULT '0',
  `reprovado` tinyint(1) NOT NULL DEFAULT '0',
  `data_envio` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_moderacao` timestamp NULL DEFAULT NULL,
  `moderador_id` int(11) DEFAULT NULL,
  `avaliacao` int(1) DEFAULT NULL COMMENT '1-5 estrelas',
  PRIMARY KEY (`id`),
  KEY `status_mod` (`status_mod`),
  KEY `aprovado` (`aprovado`),
  KEY `moderador_id` (`moderador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `empresa` (
  `id_empresa` int(11) NOT NULL AUTO_INCREMENT,
  `nome_empresa` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `celular` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(65) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `descricao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `cnpj` varchar(18) DEFAULT NULL,
  `site` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `enderecos` (
  `id_endereco` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `logradouro` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bairro` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cidade` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `estado` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cep` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `tipo` enum('principal','filial','escritorio') DEFAULT 'principal',
  PRIMARY KEY (`id_endereco`),
  KEY `id_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `imagens_carrossel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_arquivo` varchar(255) NOT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `descricao` text,
  `ordem` int(11) DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `data_upload` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ativo` (`ativo`),
  KEY `ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `requisicoes` (
  `id_requisicao` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `horario_contato` time NOT NULL,
  `cotacao` enum('Sim','Não') NOT NULL,
  `contratacao` enum('Sim','Não') NOT NULL,
  `tipo` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `categoria` enum('Aposentado','Pensionista','Servidor Público') NOT NULL,
  `outros_info` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `data_requisicao` date NOT NULL,
  `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pendente','em_analise','aprovado','reprovado','cancelado') DEFAULT 'pendente',
  `analista_id` int(11) DEFAULT NULL,
  `observacoes` text,
  `valor_solicitado` decimal(10,2) DEFAULT NULL,
  `prazo` int(3) DEFAULT NULL,
  PRIMARY KEY (`id_requisicao`),
  KEY `id_cliente` (`id_cliente`),
  KEY `status` (`status`),
  KEY `data_requisicao` (`data_requisicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `tempo_visita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_visita` int(11) NOT NULL,
  `tempo` time NOT NULL,
  `data_registro` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_visita` (`id_visita`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `reset_senha` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `data_solicitacao` timestamp DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pendente','processado','expirado') DEFAULT 'pendente',
  `nova_senha` varchar(255) DEFAULT NULL,
  `data_processamento` timestamp NULL DEFAULT NULL,
  `expira_em` timestamp DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `token` (`token`),
  KEY `status` (`status`),
  KEY `expira_em` (`expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `previsoes_pico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_previsao` date NOT NULL,
  `hora_previsao` time NOT NULL,
  `previsao_visitas` int(11) NOT NULL,
  `modelo_usado` varchar(50) DEFAULT 'PHP-SimpleML',
  `acuracia` decimal(5,2) DEFAULT NULL,
  `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
  `fator_ajuste` decimal(5,2) DEFAULT NULL,
  `classificacao` enum('normal','medio','alto') DEFAULT 'normal',
  PRIMARY KEY (`id`),
  KEY `data_previsao` (`data_previsao`),
  KEY `hora_previsao` (`hora_previsao`),
  KEY `classificacao` (`classificacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `logs_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela_afetada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_antigos` text,
  `dados_novos` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `data_hora` timestamp DEFAULT CURRENT_TIMESTAMP,
  `tipo` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','RESET_SENHA') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `acao` (`acao`),
  KEY `data_hora` (`data_hora`),
  KEY `tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `configuracoes_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text,
  `tipo` enum('string','integer','boolean','json') DEFAULT 'string',
  `descricao` varchar(255) DEFAULT NULL,
  `data_atualizacao` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- +statement
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(100) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('info','success','warning','danger') DEFAULT 'info',
  `usuario_id` int(11) DEFAULT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_leitura` timestamp NULL DEFAULT NULL,
  `acao_url` varchar(255) DEFAULT NULL,
  `expira_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `lida` (`lida`),
  KEY `tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
