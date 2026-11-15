<?php
/**
 * ========================================
 * CLASSE VALIDADOR - Validação de Formulários
 * Validações corporativas e pessoais
 * Data: 07/02/2026
 * ========================================
 */

class ValidadorFormulario {
    
    private $erros = [];
    private $dados = [];
    
    /**
     * Construtor
     */
    public function __construct($dados = []) {
        $this->dados = $dados;
    }
    
    /**
     * Adicionar dado para validação
     */
    public function adicionar_dado($chave, $valor) {
        $this->dados[$chave] = $valor;
        return $this;
    }
    
    /**
     * Validar campo obrigatório
     */
    public function requerido($campo, $mensagem = null) {
        if (empty($this->dados[$campo] ?? null)) {
            $msg = $mensagem ?? "O campo '$campo' é obrigatório";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar comprimento mínimo
     */
    public function min_comprimento($campo, $min, $mensagem = null) {
        if (isset($this->dados[$campo]) && strlen($this->dados[$campo]) < $min) {
            $msg = $mensagem ?? "O campo '$campo' deve ter no mínimo $min caracteres";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar comprimento máximo
     */
    public function max_comprimento($campo, $max, $mensagem = null) {
        if (isset($this->dados[$campo]) && strlen($this->dados[$campo]) > $max) {
            $msg = $mensagem ?? "O campo '$campo' deve ter no máximo $max caracteres";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar email
     */
    public function email($campo, $mensagem = null) {
        if (isset($this->dados[$campo]) && !filter_var($this->dados[$campo], FILTER_VALIDATE_EMAIL)) {
            $msg = $mensagem ?? "O campo '$campo' deve ser um email válido";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar CPF
     */
    public function cpf($campo, $mensagem = null) {
        if (isset($this->dados[$campo])) {
            $cpf = preg_replace('/[^0-9]/', '', $this->dados[$campo]);
            
            if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
                $msg = $mensagem ?? "CPF inválido";
                $this->erros[$campo] = $msg;
            }
        }
        return $this;
    }
    
    /**
     * Validar CNPJ
     */
    public function cnpj($campo, $mensagem = null) {
        if (isset($this->dados[$campo])) {
            $cnpj = preg_replace('/[^0-9]/', '', $this->dados[$campo]);
            
            if (strlen($cnpj) != 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
                $msg = $mensagem ?? "CNPJ inválido";
                $this->erros[$campo] = $msg;
            } else {
                // Validar dígitos verificadores do CNPJ
                if (!$this->validar_cnpj_digitos($cnpj)) {
                    $msg = $mensagem ?? "CNPJ inválido";
                    $this->erros[$campo] = $msg;
                }
            }
        }
        return $this;
    }
    
    /**
     * Validar telefone
     */
    public function telefone($campo, $mensagem = null) {
        if (isset($this->dados[$campo])) {
            $telefone = preg_replace('/[^0-9]/', '', $this->dados[$campo]);
            
            // Aceitar telefone com 10 ou 11 dígitos
            if (strlen($telefone) < 10 || strlen($telefone) > 11) {
                $msg = $mensagem ?? "Telefone inválido";
                $this->erros[$campo] = $msg;
            }
        }
        return $this;
    }
    
    /**
     * Validar URL
     */
    public function url($campo, $mensagem = null) {
        if (isset($this->dados[$campo]) && !filter_var($this->dados[$campo], FILTER_VALIDATE_URL)) {
            $msg = $mensagem ?? "URL inválida";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar número
     */
    public function numero($campo, $mensagem = null) {
        if (isset($this->dados[$campo]) && !is_numeric($this->dados[$campo])) {
            $msg = $mensagem ?? "O campo '$campo' deve ser um número";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar campo numérico com intervalo
     */
    public function intervalo($campo, $min, $max, $mensagem = null) {
        if (isset($this->dados[$campo])) {
            $valor = floatval($this->dados[$campo]);
            if ($valor < $min || $valor > $max) {
                $msg = $mensagem ?? "O campo '$campo' deve estar entre $min e $max";
                $this->erros[$campo] = $msg;
            }
        }
        return $this;
    }
    
    /**
     * Validar data
     */
    public function data($campo, $formato = 'Y-m-d', $mensagem = null) {
        if (isset($this->dados[$campo])) {
            $data = DateTime::createFromFormat($formato, $this->dados[$campo]);
            if (!$data || $data->format($formato) !== $this->dados[$campo]) {
                $msg = $mensagem ?? "Data inválida";
                $this->erros[$campo] = $msg;
            }
        }
        return $this;
    }
    
    /**
     * Validar padrão regex
     */
    public function padrao($campo, $regex, $mensagem = null) {
        if (isset($this->dados[$campo]) && !preg_match($regex, $this->dados[$campo])) {
            $msg = $mensagem ?? "O campo '$campo' não segue o padrão esperado";
            $this->erros[$campo] = $msg;
        }
        return $this;
    }
    
    /**
     * Validar comparação entre campos
     */
    public function comparar($campo1, $campo2, $mensagem = null) {
        if (isset($this->dados[$campo1], $this->dados[$campo2])) {
            if ($this->dados[$campo1] !== $this->dados[$campo2]) {
                $msg = $mensagem ?? "Os campos '$campo1' e '$campo2' não coincidem";
                $this->erros[$campo1] = $msg;
            }
        }
        return $this;
    }
    
    /**
     * Validar arquivo de upload
     */
    public function arquivo($campo, $tipos_permitidos = [], $tamanho_maximo = 5242880, $mensagem = null) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            $msg = $mensagem ?? "Erro ao fazer upload do arquivo";
            $this->erros[$campo] = $msg;
            return $this;
        }
        
        $arquivo = $_FILES[$campo];
        
        // Verificar tamanho
        if ($arquivo['size'] > $tamanho_maximo) {
            $msg = $mensagem ?? "Arquivo muito grande. Máximo: " . ($tamanho_maximo / 1048576) . "MB";
            $this->erros[$campo] = $msg;
        }
        
        // Verificar tipo
        if (!empty($tipos_permitidos)) {
            $tipo = mime_content_type($arquivo['tmp_name']);
            if (!in_array($tipo, $tipos_permitidos)) {
                $msg = $mensagem ?? "Tipo de arquivo não permitido";
                $this->erros[$campo] = $msg;
            }
        }
        
        return $this;
    }
    
    /**
     * Validar dígitos verificadores do CNPJ
     */
    private function validar_cnpj_digitos($cnpj) {
        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos;
            $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ($resultado != $digitos[0]) {
            return false;
        }
        
        $tamanho = strlen($cnpj) - 1;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos;
            $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        return $resultado == $digitos[0];
    }
    
    /**
     * Verificar se há erros
     */
    public function tem_erros() {
        return count($this->erros) > 0;
    }
    
    /**
     * Obter erros
     */
    public function obter_erros() {
        return $this->erros;
    }
    
    /**
     * Obter erro de um campo específico
     */
    public function obter_erro($campo) {
        return $this->erros[$campo] ?? null;
    }
    
    /**
     * Obter dados validados
     */
    public function obter_dados_validados() {
        if ($this->tem_erros()) {
            return null;
        }
        return $this->dados;
    }
    
    /**
     * Limpar erros
     */
    public function limpar_erros() {
        $this->erros = [];
        return $this;
    }
}

?>
