<?php
require_once __DIR__ . '/config.php';

class ConfigClientes {
    const TIPOS_CLIENTE = [
        'mayorista' => 'Mayorista',
        'minorista' => 'Minorista', 
        'may_min' => 'Mayorista/Minorista'
    ];
    
    const TIPOS_IDENTIFICACION = [
        'CUIT' => 'CUIT - Argentina',
        'RUC' => 'RUC - Perú, Ecuador, Uruguay',
        'RFC' => 'RFC - México',
        'CNPJ' => 'CNPJ - Brasil (Empresas)',
        'CPF' => 'CPF - Brasil (Personas)',
        'NIT' => 'NIT - Colombia, Bolivia',
        'RIF' => 'RIF - Venezuela',
        'CEDULA' => 'Cédula - Varios países',
        'DUI' => 'DUI - El Salvador',
        'RTN' => 'RTN - Honduras',
        'RNC' => 'RNC - República Dominicana',
        'CI' => 'CI - Cédula de Identidad',
        'DNI' => 'DNI - Documento Nacional',
        'PASAPORTE' => 'Pasaporte - Extranjeros'
    ];
    
    const PAISES = [
        'Argentina', 'México', 'Brasil', 'Colombia', 'Perú', 'Chile',
        'Ecuador', 'Venezuela', 'Uruguay', 'Bolivia', 'Paraguay',
        'Costa Rica', 'Panamá', 'Guatemala', 'El Salvador', 'Honduras',
        'Nicaragua', 'República Dominicana', 'España', 'Estados Unidos',
        'Portugal', 'Italia', 'Francia', 'Alemania', 'Japón'
    ];
    
    public static function getTiposCliente() {
        return self::TIPOS_CLIENTE;
    }
    
    public static function getTiposIdentificacion() {
        return self::TIPOS_IDENTIFICACION;
    }
    
    public static function getPaises() {
        return self::PAISES;
    }
}

function obtenerEstadisticasClientes($pdo) {
    try {
        $stats = [];
        
        $sql = "SELECT COUNT(*) as total FROM clientes";
        $stmt = $pdo->query($sql);
        $stats['total'] = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) as activos FROM clientes WHERE activo = 1";
        $stmt = $pdo->query($sql);
        $stats['activos'] = $stmt->fetchColumn();
        
        $sql = "SELECT tipo_cliente, COUNT(*) as cantidad FROM clientes WHERE tipo_cliente IS NOT NULL AND activo = 1 GROUP BY tipo_cliente ORDER BY cantidad DESC";
        $stmt = $pdo->query($sql);
        $stats['por_tipo'] = $stmt->fetchAll();
        
        $sql = "SELECT pais, COUNT(*) as cantidad FROM clientes WHERE pais IS NOT NULL AND activo = 1 GROUP BY pais ORDER BY cantidad DESC LIMIT 5";
        $stmt = $pdo->query($sql);
        $stats['por_pais'] = $stmt->fetchAll();
        
        return $stats;
        
    } catch (Exception $e) {
        return [
            'total' => 0,
            'activos' => 0,
            'por_tipo' => [],
            'por_pais' => []
        ];
    }
}

function generarCodigoCliente($pdo) {
    try {
        $sql = "SELECT codigo FROM clientes WHERE codigo LIKE 'CLIE-%' ORDER BY codigo DESC LIMIT 1";
        $stmt = $pdo->query($sql);
        $ultimo = $stmt->fetch();
        
        if ($ultimo) {
            $numero = intval(substr($ultimo['codigo'], 5)) + 1;
        } else {
            $numero = 1;
        }
        
        return 'CLIE-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        return 'CLIE-' . date('ymd') . rand(100, 999);
    }
}

function buscarClientes($pdo, $filtros = []) {
    try {
        $sql = "SELECT * FROM clientes WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['nombre'])) {
            $sql .= " AND (nombre LIKE ? OR apellido LIKE ?)";
            $params[] = '%' . $filtros['nombre'] . '%';
            $params[] = '%' . $filtros['nombre'] . '%';
        }
        
        if (!empty($filtros['empresa'])) {
            $sql .= " AND empresa LIKE ?";
            $params[] = '%' . $filtros['empresa'] . '%';
        }
        
        if (!empty($filtros['email'])) {
            $sql .= " AND email LIKE ?";
            $params[] = '%' . $filtros['email'] . '%';
        }
        
        if (!empty($filtros['tipo_cliente'])) {
            $sql .= " AND tipo_cliente = ?";
            $params[] = $filtros['tipo_cliente'];
        }
        
        if (!empty($filtros['pais'])) {
            $sql .= " AND pais = ?";
            $params[] = $filtros['pais'];
        }
        
        if (isset($filtros['activo'])) {
            $sql .= " AND activo = ?";
            $params[] = $filtros['activo'];
        }
        
        $sql .= " ORDER BY nombre, apellido";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

define('CLIENTES_POR_PAGINA', 20)
