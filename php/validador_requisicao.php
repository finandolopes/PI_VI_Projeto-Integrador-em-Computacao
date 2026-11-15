<?php
/**
 * ========================================
 * VALIDADOR DE REQUISIÇÃO
 * Regras de negócio e validações
 * ========================================
 */

class ValidadorRequisicao {
    private $dados;
    private $erros = [];
    private $avisos = [];
    
    public function __construct($dados) {
        $this->dados = $this->sanitizar($dados);
    }
    
    /**
     * Sanitizar entrada de dados
     */
    private function sanitizar($dados) {
        $sanitizado = [];
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $sanitizado[$chave] = $this->sanitizar($valor);
            } else {
                $sanitizado[$chave] = htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitizado;
    }
    
    /**
     * Validar todos os campos obrigatórios
     */
    public function validar() {
        // Validações básicas
        $this->validarNome();
        $this->validarEmail();
        $this->validarTelefone();
        $this->validarCPF();
        $this->validarDataNascimento();
        $this->validarValorSolicitado();
        $this->validarTipoRequisicao();
        $this->validarDocumentos();
        $this->validarTermos();
        
        // Validações de negócio
        $this->validarRequisicoesPendentes();
        $this->validarFrequenciaRequisicoes();
        $this->validarLimiteCredito();
        
        return empty($this->erros);
    }
    
    private function validarNome() {
        if (empty($this->dados['nome'])) {
            $this->erros[] = "Nome é obrigatório";
            return;
        }
        
        if (strlen($this->dados['nome']) < 3) {
            $this->erros[] = "Nome deve ter no mínimo 3 caracteres";
            return;
        }
        
        if (strlen($this->dados['nome']) > 150) {
            $this->erros[] = "Nome não pode exceder 150 caracteres";
            return;
        }
        
        if (!preg_match('/^[a-záéíóúâêôãõç\s]+$/i', $this->dados['nome'])) {
            $this->erros[] = "Nome contém caracteres inválidos";
        }
    }
    
    private function validarEmail() {
        if (empty($this->dados['email'])) {
            $this->erros[] = "E-mail é obrigatório";
            return;
        }
        
        if (!filter_var($this->dados['email'], FILTER_VALIDATE_EMAIL)) {
            $this->erros[] = "E-mail inválido";
            return;
        }
        
        if (strlen($this->dados['email']) > 100) {
            $this->erros[] = "E-mail muito longo";
        }
    }
    
    private function validarTelefone() {
        if (empty($this->dados['telefone'])) {
            $this->erros[] = "Telefone é obrigatório";
            return;
        }
        
        $telefone = preg_replace('/\D/', '', $this->dados['telefone']);
        
        if (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $this->erros[] = "Telefone deve ter 10 ou 11 dígitos";
            return;
        }
        
        // Validar padrão brasileiro (não pode ser todos iguais)
        if (count(array_unique(str_split($telefone))) === 1) {
            $this->erros[] = "Telefone inválido";
        }
    }
    
    private function validarCPF() {
        if (empty($this->dados['cpf'])) {
            $this->erros[] = "CPF é obrigatório";
            return;
        }
        
        $cpf = preg_replace('/\D/', '', $this->dados['cpf']);
        
        // Validar formato
        if (strlen($cpf) !== 11) {
            $this->erros[] = "CPF deve ter 11 dígitos";
            return;
        }
        
        // Validar CPF (algoritmo)
        if (!$this->validarCPFAlgoritmo($cpf)) {
            $this->erros[] = "CPF inválido";
        }
    }
    
    private function validarCPFAlgoritmo($cpf) {
        // CPF não pode ter todos os dígitos iguais
        if (count(array_unique(str_split($cpf))) === 1) {
            return false;
        }
        
        // Calcular primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;
        
        if ($cpf[9] != $digito1) {
            return false;
        }
        
        // Calcular segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;
        
        return $cpf[10] == $digito2;
    }
    
    private function validarDataNascimento() {
        if (empty($this->dados['data_nascimento'])) {
            $this->erros[] = "Data de nascimento é obrigatória";
            return;
        }
        
        $data = DateTime::createFromFormat('Y-m-d', $this->dados['data_nascimento']);
        
        if (!$data || $data->format('Y-m-d') !== $this->dados['data_nascimento']) {
            $this->erros[] = "Data de nascimento inválida";
            return;
        }
        
        $hoje = new DateTime();
        $idade = $hoje->diff($data)->y;
        
        if ($idade < 18) {
            $this->erros[] = "Deve ter pelo menos 18 anos";
            return;
        }
        
        if ($idade > 120) {
            $this->erros[] = "Data de nascimento inválida (idade não realista)";
        }
    }
    
    private function validarValorSolicitado() {
        if (empty($this->dados['valor_solicitado'])) {
            $this->erros[] = "Valor solicitado é obrigatório";
            return;
        }
        
        $valor = floatval(str_replace(',', '.', $this->dados['valor_solicitado']));
        
        if ($valor < 1000) {
            $this->erros[] = "Valor mínimo é R$ 1.000,00";
            return;
        }
        
        if ($valor > 100000) {
            $this->avisos[] = "Valor elevado - análise manual necessária";
        }
        
        if (!is_numeric($valor)) {
            $this->erros[] = "Valor deve ser um número válido";
        }
    }
    
    private function validarTipoRequisicao() {
        $tipos_validos = ['pf', 'pj', 'rural', 'microempresa'];
        
        if (empty($this->dados['tipo_requisicao'])) {
            $this->erros[] = "Tipo de requisição é obrigatório";
            return;
        }
        
        if (!in_array($this->dados['tipo_requisicao'], $tipos_validos)) {
            $this->erros[] = "Tipo de requisição inválido";
        }
    }
    
    private function validarDocumentos() {
        $docs_obrigatorios = ['rg', 'cpf_copia', 'comprovante_renda'];
        
        foreach ($docs_obrigatorios as $doc) {
            if (empty($this->dados['documentos'][$doc] ?? null)) {
                $this->erros[] = "Documento obrigatório faltando: " . $doc;
            }
        }
    }
    
    private function validarTermos() {
        if (empty($this->dados['aceita_termos'])) {
            $this->erros[] = "Deve aceitar os termos e condições";
        }
    }
    
    /**
     * Validar regras de negócio
     */
    private function validarRequisicoesPendentes() {
        // Verificar se cliente tem requisições pendentes
        global $conexao;
        
        $stmt = $conexao->prepare(
            "SELECT COUNT(*) as total FROM requisicoes 
             WHERE email = ? AND status IN ('pendente', 'analise')"
        );
        $stmt->bind_param('s', $this->dados['email']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['total'] > 0) {
            $this->erros[] = "Você já possui requisições em análise. Aguarde conclusão.";
        }
    }
    
    private function validarFrequenciaRequisicoes() {
        // Não permitir mais de 3 requisições por mês
        global $conexao;
        
        $data_limite = date('Y-m-d', strtotime('-30 days'));
        
        $stmt = $conexao->prepare(
            "SELECT COUNT(*) as total FROM requisicoes 
             WHERE email = ? AND data_requisicao > ?"
        );
        $stmt->bind_param('ss', $this->dados['email'], $data_limite);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['total'] >= 3) {
            $this->erros[] = "Limite de requisições por mês atingido. Tente novamente em " . date('d/m', strtotime('+1 month'));
        }
    }
    
    private function validarLimiteCredito() {
        // Validar limite de crédito do cliente
        global $conexao;
        
        $stmt = $conexao->prepare(
            "SELECT limite_credito FROM clientes WHERE email = ?"
        );
        $stmt->bind_param('s', $this->dados['email']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result && $this->dados['valor_solicitado'] > $result['limite_credito']) {
            $this->erros[] = "Valor solicitado excede seu limite de crédito (R$ " . 
                           number_format($result['limite_credito'], 2, ',', '.') . ")";
        }
    }
    
    public function erros() {
        return $this->erros;
    }
    
    public function avisos() {
        return $this->avisos;
    }
    
    public function temErros() {
        return !empty($this->erros);
    }
    
    public function getDados() {
        return $this->dados;
    }
}

?>
