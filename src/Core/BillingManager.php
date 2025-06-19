<?php

namespace DiscadorV2\Core;

require_once __DIR__ . '/../config/pdo.php';

use PDO;
use Exception;

class BillingManager {
    private $pdo;
    
    // Tarifas base por tipo de destino (R$ por minuto)
    private $tarifas = [
        'fixo_local' => 0.08,
        'fixo_ddd' => 0.12,
        'celular_local' => 0.35,
        'celular_ddd' => 0.45,
        'internacional' => 2.50,
        'especial' => 1.20 // 0800, 4004, etc
    ];
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Calcular custo de uma ligação
     */
    public function calculateCallCost($numero, $duracao, $empresaId = null) {
        try {
            $tipoDestino = $this->classifyDestination($numero);
            $tarifaBase = $this->getTarifa($tipoDestino, $empresaId);
            
            // Duração em minutos (mínimo 1 minuto)
            $duracaoMinutos = max(1, ceil($duracao / 60));
            
            $custo = $duracaoMinutos * $tarifaBase;
            
            return [
                'success' => true,
                'custo' => $custo,
                'tipo_destino' => $tipoDestino,
                'tarifa_minuto' => $tarifaBase,
                'duracao_minutos' => $duracaoMinutos,
                'duracao_segundos' => $duracao
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao calcular custo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Classificar destino da ligação
     */
    private function classifyDestination($numero) {
        // Remove caracteres não numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // Números especiais
        if (preg_match('/^(0800|4004|3003|0300)/', $numero)) {
            return 'especial';
        }
        
        // Internacional (código do país)
        if (strlen($numero) > 11 || preg_match('/^00/', $numero)) {
            return 'internacional';
        }
        
        // Brasil - remover código do país se presente
        if (preg_match('/^55/', $numero) && strlen($numero) > 11) {
            $numero = substr($numero, 2);
        }
        
        // Celular (9 dígitos no final)
        if (strlen($numero) == 11 && preg_match('/^[1-9][1-9]9[0-9]{8}$/', $numero)) {
            // Verificar se é mesmo DDD
            $ddd = substr($numero, 0, 2);
            return $this->isSameDDD($ddd) ? 'celular_local' : 'celular_ddd';
        }
        
        // Fixo (8 dígitos no final)
        if (strlen($numero) == 10 && preg_match('/^[1-9][1-9][2-5][0-9]{7}$/', $numero)) {
            $ddd = substr($numero, 0, 2);
            return $this->isSameDDD($ddd) ? 'fixo_local' : 'fixo_ddd';
        }
        
        // Default para fixo local
        return 'fixo_local';
    }
    
    /**
     * Verificar se é mesmo DDD (simplificado - configurável por empresa)
     */
    private function isSameDDD($ddd) {
        // Por simplicidade, considerando São Paulo (11) como local
        // Em produção, seria configurável por empresa
        return $ddd == '11';
    }
      /**
     * Obter tarifa por tipo e empresa
     */
    private function getTarifa($tipoDestino, $empresaId = null) {
        try {
            // Buscar tarifa personalizada da empresa
            if ($empresaId) {
                $stmt = $this->pdo->prepare("
                    SELECT tarifa_por_minuto 
                    FROM tarifas_empresa 
                    WHERE empresa_id = ? AND tipo_destino = ? AND ativo = 1
                ");
                $stmt->execute([$empresaId, $tipoDestino]);
                $tarifa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tarifa) {
                    return $tarifa['tarifa_por_minuto'];
                }
            }
            
            // Usar tarifa padrão
            return $this->tarifas[$tipoDestino] ?? $this->tarifas['fixo_local'];
            
        } catch (Exception $e) {
            return $this->tarifas['fixo_local'];
        }
    }
    
    /**
     * Processar custos do CDR
     */
    public function processCallCosts($empresaId, $dataInicio = null, $dataFim = null) {
        try {
            $dataInicio = $dataInicio ?: date('Y-m-01'); // Primeiro dia do mês
            $dataFim = $dataFim ?: date('Y-m-t'); // Último dia do mês
            
            // Buscar chamadas sem custo calculado
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.dst, c.duration, c.calldate, c.campanha_id
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND (c.custo IS NULL OR c.custo = 0)
                AND c.duration > 0
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $chamadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalProcessadas = 0;
            $custoTotal = 0;
            
            foreach ($chamadas as $chamada) {
                $resultado = $this->calculateCallCost(
                    $chamada['dst'], 
                    $chamada['duration'], 
                    $empresaId
                );
                
                if ($resultado['success']) {
                    // Atualizar CDR com custo
                    $stmt = $this->pdo->prepare("
                        UPDATE cdr 
                        SET custo = ?, tipo_destino = ?, tarifa_minuto = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $resultado['custo'],
                        $resultado['tipo_destino'],
                        $resultado['tarifa_minuto'],
                        $chamada['id']
                    ]);
                    
                    $totalProcessadas++;
                    $custoTotal += $resultado['custo'];
                }
            }
            
            // Atualizar billing da empresa
            $this->updateCompanyBilling($empresaId, $dataInicio, $dataFim);
            
            return [
                'success' => true,
                'message' => "Processadas {$totalProcessadas} chamadas",
                'total_processadas' => $totalProcessadas,
                'custo_total' => $custoTotal
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar custos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar billing da empresa
     */
    private function updateCompanyBilling($empresaId, $dataInicio, $dataFim) {
        try {
            // Calcular totais do período
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_chamadas,
                    SUM(duration) as total_segundos,
                    SUM(custo) as total_custo,
                    COUNT(CASE WHEN disposition = 'ANSWERED' THEN 1 END) as chamadas_atendidas
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $totais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Inserir/atualizar registro de billing
            $stmt = $this->pdo->prepare("
                INSERT INTO billing_empresa (
                    empresa_id, periodo_inicio, periodo_fim, 
                    total_chamadas, total_segundos, total_custo, chamadas_atendidas,
                    custo_medio_chamada, taxa_atendimento,
                    status, criado_em, atualizado_em
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    total_chamadas = VALUES(total_chamadas),
                    total_segundos = VALUES(total_segundos),
                    total_custo = VALUES(total_custo),
                    chamadas_atendidas = VALUES(chamadas_atendidas),
                    custo_medio_chamada = VALUES(custo_medio_chamada),
                    taxa_atendimento = VALUES(taxa_atendimento),
                    atualizado_em = NOW()
            ");
            
            $custoMedio = $totais['total_chamadas'] > 0 ? 
                $totais['total_custo'] / $totais['total_chamadas'] : 0;
            
            $taxaAtendimento = $totais['total_chamadas'] > 0 ? 
                ($totais['chamadas_atendidas'] / $totais['total_chamadas']) * 100 : 0;
            
            $stmt->execute([
                $empresaId,
                $dataInicio,
                $dataFim,
                $totais['total_chamadas'],
                $totais['total_segundos'],
                $totais['total_custo'],
                $totais['chamadas_atendidas'],
                $custoMedio,
                $taxaAtendimento
            ]);
            
        } catch (Exception $e) {
            // Log silencioso
        }
    }
    
    /**
     * Buscar billing por empresa e período
     */
    public function getBillingByCompany($empresaId, $ano = null, $mes = null) {
        try {
            $ano = $ano ?: date('Y');
            $mes = $mes ?: date('m');
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    b.*,
                    e.nome as empresa_nome,
                    e.email as empresa_email
                FROM billing_empresa b
                JOIN empresas e ON b.empresa_id = e.id
                WHERE b.empresa_id = ? 
                AND YEAR(b.periodo_inicio) = ?
                AND MONTH(b.periodo_inicio) = ?
                ORDER BY b.periodo_inicio DESC
            ");
            $stmt->execute([$empresaId, $ano, $mes]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Relatório financeiro detalhado
     */
    public function getFinancialReport($empresaId, $dataInicio, $dataFim) {
        try {
            // Relatório por campanha
            $stmt = $this->pdo->prepare("
                SELECT 
                    camp.nome as campanha,
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo,
                    COUNT(CASE WHEN c.disposition = 'ANSWERED' THEN 1 END) as chamadas_atendidas,
                    AVG(c.custo) as custo_medio,
                    c.tipo_destino,
                    COUNT(*) as chamadas_por_tipo
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
                GROUP BY camp.id, c.tipo_destino
                ORDER BY camp.nome, total_custo DESC
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $detalheCampanhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Resumo por tipo de destino
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.tipo_destino,
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo,
                    AVG(c.custo) as custo_medio,
                    AVG(c.tarifa_minuto) as tarifa_media
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
                GROUP BY c.tipo_destino
                ORDER BY total_custo DESC
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $resumoTipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Totais gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo,
                    COUNT(CASE WHEN c.disposition = 'ANSWERED' THEN 1 END) as chamadas_atendidas,
                    MIN(DATE(c.calldate)) as primeira_chamada,
                    MAX(DATE(c.calldate)) as ultima_chamada
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $totais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ],
                'totais' => $totais,
                'detalhe_campanhas' => $detalheCampanhas,
                'resumo_tipos' => $resumoTipos
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar fatura por ID
     */
    public function getInvoiceById($billingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    b.*,
                    e.nome as empresa_nome,
                    e.email as empresa_email,
                    e.cnpj as empresa_cnpj,
                    e.endereco as empresa_endereco
                FROM billing_empresa b
                JOIN empresas e ON b.empresa_id = e.id
                WHERE b.id = ?
            ");
            $stmt->execute([$billingId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Marcar fatura como paga
     */
    public function markInvoiceAsPaid($billingId, $paymentData = []) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE billing_empresa 
                SET status = 'pago', 
                    data_pagamento = NOW(),
                    forma_pagamento = ?,
                    referencia_pagamento = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $paymentData['forma_pagamento'] ?? 'manual',
                $paymentData['referencia'] ?? null,
                $billingId
            ]);
            
            return [
                'success' => true,
                'message' => 'Fatura marcada como paga com sucesso'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao marcar fatura como paga: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar estatísticas de billing
     */
    public function getBillingStats($empresaId = null) {
        try {
            $whereClause = $empresaId ? "WHERE b.empresa_id = ?" : "";
            $params = $empresaId ? [$empresaId] : [];
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_faturas,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as faturas_pendentes,
                    SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as faturas_pagas,
                    SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) as faturas_vencidas,
                    SUM(total_custo) as receita_total,
                    SUM(CASE WHEN status = 'pago' THEN total_custo ELSE 0 END) as receita_recebida,
                    SUM(CASE WHEN status = 'pendente' THEN total_custo ELSE 0 END) as receita_pendente
                FROM billing_empresa b
                {$whereClause}
            ");
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [
                'total_faturas' => 0,
                'faturas_pendentes' => 0,
                'faturas_pagas' => 0,
                'faturas_vencidas' => 0,
                'receita_total' => 0,
                'receita_recebida' => 0,
                'receita_pendente' => 0
            ];
        }
    }
      /**
     * Configurar tarifas personalizadas para empresa
     */
    public function setCustomTariffs($empresaId, $tarifas) {
        try {
            // Desativar tarifas antigas
            $stmt = $this->pdo->prepare("
                UPDATE tarifas_empresa 
                SET ativo = 0, atualizado_em = NOW() 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresaId]);
            
            // Inserir novas tarifas
            $stmt = $this->pdo->prepare("
                INSERT INTO tarifas_empresa (
                    empresa_id, tipo_destino, tarifa_por_minuto, ativo, criado_em
                ) VALUES (?, ?, ?, 1, NOW())
            ");
            
            foreach ($tarifas as $tipo => $valor) {
                $stmt->execute([$empresaId, $tipo, $valor]);
            }
            
            return [
                'success' => true,
                'message' => 'Tarifas atualizadas com sucesso'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar tarifas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Processar cobrança de chamada em tempo real
     */
    public function processRealTimeCallCost($empresaId, $numeroDestino, $duracaoSegundos, $campanhaId = null, $usuarioId = null, $uniqueid = null, $canal = null) {
        try {
            $resultado = $this->calculateCallCost($numeroDestino, $duracaoSegundos, $empresaId);
            
            if (!$resultado['success']) {
                return $resultado;
            }
            
            // Registrar na tabela de billing_chamadas
            $stmt = $this->pdo->prepare("
                INSERT INTO billing_chamadas (
                    empresa_id, campanha_id, usuario_id, numero_destino,
                    tipo_destino, duracao_segundos, duracao_minutos,
                    tarifa_por_minuto, custo_total, uniqueid, canal,
                    data_chamada, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'processado')
            ");
            
            $stmt->execute([
                $empresaId,
                $campanhaId,
                $usuarioId,
                $numeroDestino,
                $resultado['tipo_destino'],
                $duracaoSegundos,
                $resultado['duracao_minutos'],
                $resultado['tarifa_minuto'],
                $resultado['custo'],
                $uniqueid,
                $canal
            ]);
            
            // Debitar do crédito disponível
            $stmt = $this->pdo->prepare("
                UPDATE empresas 
                SET credito_disponivel = credito_disponivel - ?
                WHERE id = ?
            ");
            $stmt->execute([$resultado['custo'], $empresaId]);
            
            // Verificar limite de crédito
            $creditoAtual = $this->getCompanyCredit($empresaId);
            if ($creditoAtual <= 0) {
                $this->createBillingAlert($empresaId, 'limite_credito', 'Crédito Esgotado', 
                    'O crédito disponível foi esgotado. Recarregue para continuar as operações.', 'critical');
            }
            
            return [
                'success' => true,
                'custo_cobrado' => $resultado['custo'],
                'credito_restante' => $creditoAtual - $resultado['custo']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar cobrança: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar fatura mensal
     */
    public function generateMonthlyInvoice($empresaId, $mes = null, $ano = null) {
        try {
            $mes = $mes ?: date('n');
            $ano = $ano ?: date('Y');
            
            // Verificar se já existe fatura
            $stmt = $this->pdo->prepare("
                SELECT id FROM billing_faturas 
                WHERE empresa_id = ? AND mes_referencia = ? AND ano_referencia = ?
            ");
            $stmt->execute([$empresaId, $mes, $ano]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Fatura já existe para este período'
                ];
            }
            
            // Calcular valor das chamadas
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(custo_total), 0.00) as valor_chamadas
                FROM billing_chamadas
                WHERE empresa_id = ? 
                  AND MONTH(data_chamada) = ? 
                  AND YEAR(data_chamada) = ?
                  AND status = 'processado'
            ");
            $stmt->execute([$empresaId, $mes, $ano]);
            $valorChamadas = $stmt->fetchColumn();
            
            // Buscar valor do plano
            $stmt = $this->pdo->prepare("
                SELECT valor_mensal, dia_vencimento FROM empresas WHERE id = ?
            ");
            $stmt->execute([$empresaId]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $valorPlano = $empresa['valor_mensal'] ?? 0.00;
            $diaVencimento = $empresa['dia_vencimento'] ?? 10;
            
            // Calcular data de vencimento
            $proximoMes = ($mes == 12) ? 1 : $mes + 1;
            $proximoAno = ($mes == 12) ? $ano + 1 : $ano;
            $dataVencimento = sprintf('%04d-%02d-%02d', $proximoAno, $proximoMes, $diaVencimento);
            
            // Criar fatura
            $stmt = $this->pdo->prepare("
                INSERT INTO billing_faturas (
                    empresa_id, mes_referencia, ano_referencia,
                    valor_chamadas, valor_plano, valor_total, 
                    data_vencimento, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')
            ");
            
            $valorTotal = $valorChamadas + $valorPlano;
            $stmt->execute([
                $empresaId, $mes, $ano, $valorChamadas, $valorPlano, $valorTotal, $dataVencimento
            ]);
            
            $faturaId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'fatura_id' => $faturaId,
                'valor_total' => $valorTotal,
                'data_vencimento' => $dataVencimento
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar fatura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar pagamento
     */
    public function registerPayment($faturaId, $valorPago, $formaPagamento, $transacaoId = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Buscar dados da fatura
            $stmt = $this->pdo->prepare("
                SELECT empresa_id, valor_total FROM billing_faturas WHERE id = ?
            ");
            $stmt->execute([$faturaId]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$fatura) {
                throw new Exception('Fatura não encontrada');
            }
            
            // Registrar pagamento
            $stmt = $this->pdo->prepare("
                INSERT INTO billing_pagamentos (
                    fatura_id, empresa_id, valor_pago, forma_pagamento,
                    data_pagamento, transacao_id, status
                ) VALUES (?, ?, ?, ?, NOW(), ?, 'aprovado')
            ");
            $stmt->execute([
                $faturaId, $fatura['empresa_id'], $valorPago, $formaPagamento, $transacaoId
            ]);
            
            // Atualizar status da fatura
            $stmt = $this->pdo->prepare("
                UPDATE billing_faturas 
                SET status = 'paga', data_pagamento = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$faturaId]);
            
            // Adicionar crédito à empresa
            $stmt = $this->pdo->prepare("
                UPDATE empresas 
                SET credito_disponivel = credito_disponivel + ?
                WHERE id = ?
            ");
            $stmt->execute([$valorPago, $fatura['empresa_id']]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Pagamento registrado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Erro ao registrar pagamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar alerta de billing
     */
    public function createBillingAlert($empresaId, $tipo, $titulo, $mensagem, $nivel = 'info', $faturaId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO billing_alertas (
                    empresa_id, tipo, titulo, mensagem, nivel, fatura_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$empresaId, $tipo, $titulo, $mensagem, $nivel, $faturaId]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter crédito atual da empresa
     */
    public function getCompanyCredit($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT credito_disponivel FROM empresas WHERE id = ?
            ");
            $stmt->execute([$empresaId]);
            return $stmt->fetchColumn() ?: 0.00;
        } catch (Exception $e) {
            return 0.00;
        }
    }
    
    /**
     * Obter estatísticas de billing da empresa
     */
    public function getCompanyBillingStats($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM v_billing_estatisticas WHERE empresa_id = ?
            ");
            $stmt->execute([$empresaId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [
                'custo_mes_atual' => 0,
                'chamadas_mes_atual' => 0,
                'custo_mes_anterior' => 0,
                'credito_disponivel' => 0,
                'alertas_ativos' => 0
            ];
        }
    }
    
    /**
     * Obter relatório de custos por período
     */
    public function getCostReport($empresaId, $dataInicio, $dataFim, $groupBy = 'day') {
        try {
            $groupClause = match($groupBy) {
                'hour' => 'DATE_FORMAT(data_chamada, "%Y-%m-%d %H:00:00")',
                'day' => 'DATE(data_chamada)',
                'week' => 'YEARWEEK(data_chamada)',
                'month' => 'DATE_FORMAT(data_chamada, "%Y-%m")',
                default => 'DATE(data_chamada)'
            };
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    {$groupClause} as periodo,
                    COUNT(*) as total_chamadas,
                    SUM(duracao_segundos) as duracao_total,
                    SUM(custo_total) as custo_total,
                    AVG(custo_total) as custo_medio,
                    tipo_destino,
                    COUNT(*) as chamadas_por_tipo
                FROM billing_chamadas
                WHERE empresa_id = ? 
                  AND DATE(data_chamada) BETWEEN ? AND ?
                  AND status = 'processado'
                GROUP BY periodo, tipo_destino
                ORDER BY periodo DESC, custo_total DESC
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter histórico de faturas
     */
    public function getBillingHistory($empresaId, $limit = 12) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    bf.id, bf.mes_referencia, bf.ano_referencia,
                    bf.valor_chamadas, bf.valor_plano, bf.valor_total,
                    bf.status, bf.data_vencimento, bf.data_pagamento,
                    COUNT(bc.id) as total_chamadas,
                    SUM(bc.duracao_minutos) as total_minutos
                FROM billing_faturas bf
                LEFT JOIN billing_chamadas bc ON bf.empresa_id = bc.empresa_id
                    AND MONTH(bc.data_chamada) = bf.mes_referencia
                    AND YEAR(bc.data_chamada) = bf.ano_referencia
                WHERE bf.empresa_id = ? 
                GROUP BY bf.id
                ORDER BY bf.ano_referencia DESC, bf.mes_referencia DESC
                LIMIT ?
            ");
            $stmt->execute([$empresaId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter alertas de billing
     */
    public function getBillingAlerts($empresaId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM billing_alertas 
                WHERE empresa_id = ? AND ativo = 1 
                ORDER BY nivel DESC, criado_em DESC
                LIMIT ?
            ");
            $stmt->execute([$empresaId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter tarifas da empresa
     */
    public function getCompanyTariffs($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT tipo_destino, tarifa_por_minuto 
                FROM tarifas_empresa 
                WHERE empresa_id = ? AND ativo = 1
                ORDER BY tipo_destino
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
