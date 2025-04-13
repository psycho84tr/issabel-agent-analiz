<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 4.0                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2021 Issabel Foundation                                |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
*/

require_once "modules/reports_break/libs/paloSantoReportsBreak.class.php";
require_once "modules/calls_per_agent/libs/paloSantoCallPerAgent.class.php";
require_once "modules/login_logout/libs/paloSantoLoginLogout.class.php";

class paloSantoAgentAnaliz {
    private $_DB;
    var $errMsg;

    function __construct(&$pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB =& $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if (!$this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
    }

    /**
     * Método para obtener los reportes de breaks
     *
     * @param string $sFechaInicio Fecha de inicio del reporte yyyy-mm-dd
     * @param string $sFechaFinal  Fecha de fin del reporte yyyy-mm-dd
     *
     * @return array    Arreglo con la información de los breaks
     */
    function getReportsBreakData($sFechaInicio, $sFechaFinal)
    {
        $oReportsBreak = new paloSantoReportsBreak($this->_DB);
        return $oReportsBreak->getReportesBreak($sFechaInicio, $sFechaFinal);
    }

    /**
     * Método para obtener las llamadas por agente
     *
     * @param string $sFechaInicio Fecha de inicio del reporte yyyy-mm-dd HH:MM:SS
     * @param string $sFechaFinal  Fecha de fin del reporte yyyy-mm-dd HH:MM:SS
     * @param array  $fieldPat     Arreglo con los filtros de búsqueda
     *
     * @return array    Arreglo con la información de las llamadas
     */
    function getCallsPerAgentData($sFechaInicio, $sFechaFinal, $fieldPat)
    {
        $oCallsAgent = new paloSantoCallsAgent($this->_DB);
        return $oCallsAgent->obtenerCallsAgent($sFechaInicio, $sFechaFinal, $fieldPat);
    }
    
    /**
     * Método para obtener los datos de login/logout por agente
     *
     * @param string $sFechaInicio Fecha de inicio del reporte yyyy-mm-dd HH:MM:SS
     * @param string $sFechaFinal  Fecha de fin del reporte yyyy-mm-dd HH:MM:SS
     * @param mixed $idIncomingQueue ID de la cola entrante a filtrar o NULL para todas
     *
     * @return array Arreglo con la información de sesiones (login/logout)
     */
    function getLoginLogoutData($sFechaInicio, $sFechaFinal, $idIncomingQueue = NULL)
    {
        $oLoginLogout = new paloSantoLoginLogout($this->_DB);
        $recordset = $oLoginLogout->leerRegistrosLoginLogout('G', $sFechaInicio, $sFechaFinal, $idIncomingQueue);
        return $recordset;
    }

    /**
     * Método para combinar los datos de agentes de ambos reportes
     *
     * @param array $reportsBreakData  Datos de reportes de breaks
     * @param array $callsPerAgentData Datos de llamadas por agente
     * @param array $loginLogoutData   Datos de login/logout por agente
     *
     * @return array    Arreglo combinado con la información
     */
    function mergeAgentData($reportsBreakData, $callsPerAgentData, $loginLogoutData = NULL)
    {
        $agentData = array();
        
        // Primero procesar los agentes con datos de breaks
        if (isset($reportsBreakData['reporte']) && is_array($reportsBreakData['reporte'])) {
            foreach ($reportsBreakData['reporte'] as $agentInfo) {
                $agentNumber = $agentInfo['numero_agente'];
                
                // Inicializar con datos del agente
                $agentData[$agentNumber] = array(
                    'numero_agente' => $agentNumber,
                    'nombre_agente' => $agentInfo['nombre_agente'],
                    'num_answered'  => 0,
                    'sum_duration'  => 0,
                    'avg_duration'  => 0,
                    'max_duration'  => 0,
                    'session_time'  => 0,
                    'breaks'        => array()
                );
                
                // Agregar breaks
                foreach ($agentInfo['breaks'] as $breakInfo) {
                    $agentData[$agentNumber]['breaks'][$breakInfo['id_break']] = $breakInfo['duracion'];
                }
            }
        }
        
        // Ajanların kuyruk bazlı çağrı verilerini toplamak için geçici dizi
        $agentTotals = array();
        
        // Luego procesar los agentes con datos de llamadas
        if (is_array($callsPerAgentData)) {
            foreach ($callsPerAgentData as $callInfo) {
                $agentNumber = $callInfo['agent_number'];
                
                // Bu ajan için bir toplam dizisi oluştur veya mevcut olanı kullan
                if (!isset($agentTotals[$agentNumber])) {
                    $agentTotals[$agentNumber] = array(
                        'num_answered' => 0,
                        'sum_duration' => 0,
                        'max_duration' => 0,
                        'agent_name'   => $callInfo['agent_name']
                    );
                }
                
                // Bu çağrı grubu verilerini ajan toplamlarına ekle
                $agentTotals[$agentNumber]['num_answered'] += $callInfo['num_answered'];
                $agentTotals[$agentNumber]['sum_duration'] += $callInfo['sum_duration'];
                
                // Maksimum süreyi güncelle
                if ($callInfo['max_duration'] > $agentTotals[$agentNumber]['max_duration']) {
                    $agentTotals[$agentNumber]['max_duration'] = $callInfo['max_duration'];
                }
            }
            
            // Toplam verileri agentData dizisine aktar
            foreach ($agentTotals as $agentNumber => $totals) {
                // Si el agente no existe en los datos de breaks, crearlo
                if (!isset($agentData[$agentNumber])) {
                    $agentData[$agentNumber] = array(
                        'numero_agente' => $agentNumber,
                        'nombre_agente' => $totals['agent_name'],
                        'session_time'  => 0,
                        'breaks'        => array()
                    );
                }
                
                // Actualizar datos de llamadas con los totales
                $agentData[$agentNumber]['num_answered'] = $totals['num_answered'];
                $agentData[$agentNumber]['sum_duration'] = $totals['sum_duration'];
                
                // Ortalama süre hesaplaması
                $agentData[$agentNumber]['avg_duration'] = 
                    ($totals['num_answered'] > 0) ? 
                    ($totals['sum_duration'] / $totals['num_answered']) : 0;
                
                $agentData[$agentNumber]['max_duration'] = $totals['max_duration'];
            }
        }
        
        // Oturum süresi verilerini ekle
        if (is_array($loginLogoutData)) {
            foreach ($loginLogoutData as $loginInfo) {
                $agentNumber = $loginInfo['number'];
                
                // Si el agente no existe en los datos previos, crearlo
                if (!isset($agentData[$agentNumber])) {
                    $agentData[$agentNumber] = array(
                        'numero_agente' => $agentNumber,
                        'nombre_agente' => $loginInfo['name'],
                        'num_answered'  => 0,
                        'sum_duration'  => 0,
                        'avg_duration'  => 0,
                        'max_duration'  => 0,
                        'session_time'  => 0,
                        'breaks'        => array()
                    );
                }
                
                // Oturum süresini ekle
                $agentData[$agentNumber]['session_time'] = $loginInfo['duration'];
            }
        }
        
        // Convertir array asociativo en array numérico para el grid
        $result = array();
        foreach ($agentData as $agentInfo) {
            $result[] = $agentInfo;
        }
        
        return $result;
    }
}
?> 