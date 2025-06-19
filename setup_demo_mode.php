<?php
/**
 * Mock do banco de dados para demonstração
 * Simula as operações básicas sem necessidade do MySQL
 */

class MockDatabase {
    private static $data = [
        'admin_global' => [
            [
                'id' => 1,
                'nome' => 'Administrator',
                'email' => 'admin@discador.com',
                'senha' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'ativo' => true
            ]
        ],
        'empresas' => [
            [
                'id' => 1,
                'nome' => 'Empresa Demo',
                'razao_social' => 'Empresa Demo LTDA',
                'cnpj' => '12345678000195',
                'email' => 'contato@empresademo.com',
                'telefone' => '(11) 99999-9999',
                'subdomain' => 'demo',
                'plano' => 'basico',
                'status' => 'ativa',
                'criado_em' => '2025-06-18 10:00:00'
            ]
        ],
        'usuarios' => [
            [
                'id' => 1,
                'empresa_id' => 1,
                'nome' => 'Admin Demo',
                'email' => 'admin@demo.com',
                'senha' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'nivel' => 'master',
                'ativo' => true
            ]
        ]
    ];
    
    public static function find($table, $conditions = []) {
        if (!isset(self::$data[$table])) {
            return [];
        }
        
        $results = self::$data[$table];
        
        foreach ($conditions as $field => $value) {
            $results = array_filter($results, function($row) use ($field, $value) {
                return $row[$field] === $value;
            });
        }
        
        return array_values($results);
    }
    
    public static function findOne($table, $conditions = []) {
        $results = self::find($table, $conditions);
        return empty($results) ? null : $results[0];
    }
    
    public static function insert($table, $data) {
        if (!isset(self::$data[$table])) {
            self::$data[$table] = [];
        }
        
        $data['id'] = count(self::$data[$table]) + 1;
        self::$data[$table][] = $data;
        
        return $data['id'];
    }
    
    public static function count($table, $conditions = []) {
        return count(self::find($table, $conditions));
    }
    
    public static function getAllTables() {
        return array_keys(self::$data);
    }
}

// Função para simular PDO
function getMockDatabaseConnection() {
    return new class {
        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                
                public function __construct($sql) {
                    $this->sql = $sql;
                }
                
                public function execute($params = []) {
                    // Simular execução
                    return true;
                }
                
                public function fetch() {
                    if (strpos($this->sql, 'admin_global') !== false) {
                        return MockDatabase::findOne('admin_global', ['email' => 'admin@discador.com']);
                    }
                    return false;
                }
                
                public function fetchAll() {
                    return [];
                }
                
                public function rowCount() {
                    if (strpos($this->sql, 'admin_global') !== false) {
                        return 1;
                    }
                    return 0;
                }
            };
        }
        
        public function lastInsertId() {
            return 1;
        }
        
        public function exec($sql) {
            return true;
        }
        
        public function beginTransaction() {
            return true;
        }
        
        public function commit() {
            return true;
        }
        
        public function rollBack() {
            return true;
        }
    };
}

echo "=== MODO DEMONSTRAÇÃO - MOCK DATABASE ===\n";
echo "\nInstância mock criada com sucesso!\n";
echo "\nDados de demonstração disponíveis:\n";

foreach (['admin_global', 'empresas', 'usuarios'] as $table) {
    $count = MockDatabase::count($table);
    echo "- $table: $count registros\n";
}

echo "\n============================================\n";
echo "SISTEMA MULTI-TENANT PRONTO PARA DEMO!\n";
echo "============================================\n";
echo "\nPara testar:\n";
echo "1. Acesse: http://localhost/discador_v2/src/login.php?type=admin\n";
echo "2. Login Admin Global:\n";
echo "   Email: admin@discador.com\n";
echo "   Senha: password\n";
echo "\n3. Ou acesse como empresa:\n";
echo "   URL: http://localhost/discador_v2/src/login.php?type=company\n";
echo "   Email: admin@demo.com\n";
echo "   Senha: password\n";
echo "\nNOTA: Este é um modo de demonstração sem banco real.\n";
echo "Para produção, configure corretamente o MySQL.\n";
echo "\n";
?>
