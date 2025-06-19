<?php

namespace DiscadorV2\Core;

require_once __DIR__ . '/../config/pdo.php';

use PDO;
use Exception;

class ContactListManager {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Upload de lista de contatos
     */
    public function uploadContactList($empresaId, $data, $files) {
        try {
            // Validar dados obrigatórios
            if (empty($data['nome'])) {
                return ['success' => false, 'message' => 'Nome da lista é obrigatório'];
            }
            
            if (!isset($files['arquivo']) || $files['arquivo']['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Arquivo é obrigatório'];
            }
            
            $arquivo = $files['arquivo'];
            
            // Validar tipo de arquivo
            $allowedTypes = ['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            if (!in_array($arquivo['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de arquivo não permitido. Use CSV, TXT ou XLSX'];
            }
            
            // Validar tamanho (50MB max)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($arquivo['size'] > $maxSize) {
                return ['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 50MB'];
            }
            
            // Verificar se nome já existe na empresa
            $stmt = $this->pdo->prepare("SELECT id FROM listas_contatos WHERE nome = ? AND empresa_id = ?");
            $stmt->execute([$data['nome'], $empresaId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Já existe uma lista com este nome'];
            }
            
            // Mover arquivo para diretório de uploads
            $uploadDir = __DIR__ . '/../uploads/listas/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . $arquivo['name'];
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($arquivo['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Erro ao fazer upload do arquivo'];
            }
            
            // Inserir lista no banco
            $stmt = $this->pdo->prepare("
                INSERT INTO listas_contatos (
                    empresa_id, campanha_id, nome, descricao, arquivo_original, 
                    arquivo_path, formato, tamanho_mb, status, criado_em
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processando', NOW())
            ");
            
            $formato = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
            $tamanhoMB = $arquivo['size'] / (1024 * 1024);
            
            $stmt->execute([
                $empresaId,
                $data['campanha_id'] ?: null,
                $data['nome'],
                $data['descricao'] ?: null,
                $arquivo['name'],
                $fileName,
                $formato,
                $tamanhoMB
            ]);
            
            $listId = $this->pdo->lastInsertId();
            
            // Processar arquivo e importar contatos
            $result = $this->processContactFile($listId, $filePath, $formato);
            
            if (!$result['success']) {
                // Remove arquivo e registro se falhou
                unlink($filePath);
                $this->pdo->prepare("DELETE FROM listas_contatos WHERE id = ?")->execute([$listId]);
                return $result;
            }
            
            return ['success' => true, 'message' => 'Lista carregada com sucesso', 'list_id' => $listId];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao processar lista: ' . $e->getMessage()];
        }
    }
    
    /**
     * Processar arquivo de contatos
     */
    private function processContactFile($listId, $filePath, $formato) {
        try {
            $contatos = [];
            
            switch (strtolower($formato)) {
                case 'csv':
                case 'txt':
                    $contatos = $this->parseCSV($filePath);
                    break;
                    
                case 'xlsx':
                    $contatos = $this->parseXLSX($filePath);
                    break;
                    
                default:
                    return ['success' => false, 'message' => 'Formato de arquivo não suportado'];
            }
            
            if (empty($contatos)) {
                return ['success' => false, 'message' => 'Nenhum contato válido encontrado no arquivo'];
            }
            
            // Inserir contatos no banco
            $totalImportados = 0;
            $totalErros = 0;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO contatos_lista (
                    lista_id, nome, telefone, email, dados_extras, status, criado_em
                ) VALUES (?, ?, ?, ?, ?, 'pendente', NOW())
            ");
            
            foreach ($contatos as $contato) {
                try {
                    if (empty($contato['nome']) || empty($contato['telefone'])) {
                        $totalErros++;
                        continue;
                    }
                    
                    // Limpar e validar telefone
                    $telefone = $this->formatPhone($contato['telefone']);
                    if (!$telefone) {
                        $totalErros++;
                        continue;
                    }
                    
                    $dadosExtras = [];
                    foreach ($contato as $key => $value) {
                        if (!in_array($key, ['nome', 'telefone', 'email'])) {
                            $dadosExtras[$key] = $value;
                        }
                    }
                    
                    $stmt->execute([
                        $listId,
                        $contato['nome'],
                        $telefone,
                        $contato['email'] ?? null,
                        json_encode($dadosExtras)
                    ]);
                    
                    $totalImportados++;
                    
                } catch (Exception $e) {
                    $totalErros++;
                }
            }
            
            // Atualizar lista com estatísticas
            $stmt = $this->pdo->prepare("
                UPDATE listas_contatos 
                SET total_contatos = ?, contatos_importados = ?, contatos_erros = ?, 
                    status = 'ativa', processado_em = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$totalImportados + $totalErros, $totalImportados, $totalErros, $listId]);
            
            return [
                'success' => true, 
                'message' => "Lista processada: {$totalImportados} contatos importados, {$totalErros} erros"
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao processar arquivo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Parse CSV
     */
    private function parseCSV($filePath) {
        $contatos = [];
        
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ',');
            
            if (!$headers) {
                return [];
            }
            
            // Normalizar headers
            $headers = array_map(function($header) {
                $header = strtolower(trim($header));
                $mapping = [
                    'nome' => 'nome',
                    'name' => 'nome',
                    'telefone' => 'telefone',
                    'phone' => 'telefone',
                    'fone' => 'telefone',
                    'email' => 'email',
                    'e-mail' => 'email'
                ];
                return $mapping[$header] ?? $header;
            }, $headers);
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($data) >= count($headers)) {
                    $contato = array_combine($headers, $data);
                    $contatos[] = $contato;
                }
            }
            
            fclose($handle);
        }
        
        return $contatos;
    }
    
    /**
     * Parse XLSX (simulado - em produção usar biblioteca como PhpSpreadsheet)
     */
    private function parseXLSX($filePath) {
        // Aqui seria implementado com PhpSpreadsheet
        // Por simplicidade, retornando array vazio
        return [];
    }
    
    /**
     * Formatar telefone
     */
    private function formatPhone($phone) {
        // Remove tudo que não é número
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validar formato brasileiro
        if (strlen($phone) == 10 || strlen($phone) == 11) {
            if (strlen($phone) == 10) {
                // Adicionar 9 nos celulares
                if (in_array(substr($phone, 2, 1), ['6', '7', '8', '9'])) {
                    $phone = substr($phone, 0, 2) . '9' . substr($phone, 2);
                }
            }
            return $phone;
        }
        
        return null;
    }
    
    /**
     * Buscar listas por empresa
     */
    public function getContactListsByCompany($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    l.*,
                    c.nome as campanha_nome,
                    (SELECT COUNT(*) FROM contatos_lista cl WHERE cl.lista_id = l.id) as total_contatos,
                    (SELECT COUNT(*) FROM contatos_lista cl WHERE cl.lista_id = l.id AND cl.status = 'pendente') as contatos_pendentes,
                    (SELECT COUNT(*) FROM contatos_lista cl WHERE cl.lista_id = l.id AND cl.status != 'pendente') as contatos_processados
                FROM listas_contatos l
                LEFT JOIN campanhas c ON l.campanha_id = c.id
                WHERE l.empresa_id = ?
                ORDER BY l.criado_em DESC
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Buscar campanhas por empresa
     */
    public function getCampaignsByCompany($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nome, status
                FROM campanhas 
                WHERE empresa_id = ?
                ORDER BY nome
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Excluir lista de contatos
     */
    public function deleteContactList($listId) {
        try {
            // Verificar se lista existe
            $stmt = $this->pdo->prepare("SELECT id, arquivo_path FROM listas_contatos WHERE id = ?");
            $stmt->execute([$listId]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$list) {
                return ['success' => false, 'message' => 'Lista não encontrada'];
            }
            
            // Excluir contatos relacionados
            $stmt = $this->pdo->prepare("DELETE FROM contatos_lista WHERE lista_id = ?");
            $stmt->execute([$listId]);
            
            // Excluir lista
            $stmt = $this->pdo->prepare("DELETE FROM listas_contatos WHERE id = ?");
            $stmt->execute([$listId]);
            
            // Excluir arquivo físico
            $filePath = __DIR__ . '/../uploads/listas/' . $list['arquivo_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return ['success' => true, 'message' => 'Lista excluída com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao excluir lista: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar contatos de uma lista
     */
    public function getContactsByList($listId, $limit = 100, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM contatos_lista 
                WHERE lista_id = ? 
                ORDER BY id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$listId, $limit, $offset]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Buscar lista por ID
     */
    public function getListById($listId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    l.*,
                    c.nome as campanha_nome,
                    e.nome as empresa_nome
                FROM listas_contatos l
                LEFT JOIN campanhas c ON l.campanha_id = c.id
                JOIN empresas e ON l.empresa_id = e.id
                WHERE l.id = ?
            ");
            $stmt->execute([$listId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
}
