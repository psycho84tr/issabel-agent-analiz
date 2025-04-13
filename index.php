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

//include issabel framework
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoDB.class.php";
require_once "libs/paloSantoGrid.class.php";
require_once "libs/misc.lib.php";

require_once "modules/agent_console/libs/issabel2.lib.php";

function _moduleContent(&$smarty, $module_name)
{  
    // Çıktı tamponlamasını başlat
    ob_start();
    
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoAgentAnaliz.class.php";

    global $arrConf;
    $arrConf = array_merge($arrConf, $arrConfModule);
    // Obtengo la ruta del template a utilizar
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir'])) ? $arrConf['templates_dir'] : 'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    load_language_module($module_name);

    // Abrir conexión a la base de datos
    $pDB = new paloDB($arrConf['dsn_conn_database']);
    if (!is_object($pDB->conn) || $pDB->errMsg != "") {
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$pDB->errMsg);
        return NULL;
    }

    // Cadenas estáticas a asignar
    $smarty->assign(array(
        "btn_consultar" =>  _tr('Query'),
        "module_name"   =>  $module_name,
    ));

    //actions
    $action = getAction();
    $content = "";

    switch($action) {
        default:
            $content = reportAgentAnaliz($smarty, $module_name, $local_templates_dir, $pDB);
            break;
    }
    return $content;
}

function reportAgentAnaliz($smarty, $module_name, $local_templates_dir, &$pDB)
{
    // Obtener rango de fechas de consulta. Si no existe, se asume día de hoy
    $sFechaInicio = date('d M Y');
    if (isset($_GET['txt_fecha_init'])) $sFechaInicio = $_GET['txt_fecha_init'];
    if (isset($_POST['txt_fecha_init'])) $sFechaInicio = $_POST['txt_fecha_init'];
    $sFechaFinal = date('d M Y');
    if (isset($_GET['txt_fecha_end'])) $sFechaFinal = $_GET['txt_fecha_end'];
    if (isset($_POST['txt_fecha_end'])) $sFechaFinal = $_POST['txt_fecha_end'];
    
    // Filtreleme için değişkenler
    $type = '';
    if (isset($_GET['type'])) $type = $_GET['type'];
    if (isset($_POST['type'])) $type = $_POST['type'];
    $queue = '';
    if (isset($_GET['queue'])) $queue = $_GET['queue'];
    if (isset($_POST['queue'])) $queue = $_POST['queue'];
    $number = '';
    if (isset($_GET['number'])) $number = $_GET['number'];
    if (isset($_POST['number'])) $number = $_POST['number'];
    
    $arrFilterExtraVars = array(
        "txt_fecha_init" => $sFechaInicio,
        "txt_fecha_end"  => $sFechaFinal,
        "type"           => $type,
        "queue"          => $queue,
        "number"         => $number,
    );
    
    // Combobox için değerler
    $comboTipos = array(
        ''      =>  _tr('All'),
        'IN'    =>  _tr("Ingoing"),
        'OUT'   =>  _tr("Outgoing"),
    );
    
    $arrFormElements = createFieldFilter($comboTipos);
    $oFilterForm = new paloForm($smarty, $arrFormElements);
    
    // Validación de las fechas recogidas
    if (!$oFilterForm->validateForm($arrFilterExtraVars)) {
        $smarty->assign("mb_title", _tr("Validation Error"));
        $arrErrores = $oFilterForm->arrErroresValidacion;
        $strErrorMsg = '<b>'._tr('The following fields contain errors').'</b><br/>';
        foreach($arrErrores as $k => $v) {
            $strErrorMsg .= "$k, ";
        }
        $smarty->assign("mb_message", $strErrorMsg);

        $arrFilterExtraVars = array(
            "txt_fecha_init"    => date('d M Y'),
            "txt_fecha_end"     => date('d M Y'),
            "type"              => '',
            "queue"             => '',
            "number"            => '',
        );        
    }
    
    // SHOW değişkenini smarty'e ata
    $smarty->assign("SHOW", _tr("Show"));
    
    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl", "", $arrFilterExtraVars);

    // Obtener fechas en formato yyyy-mm-dd
    $sFechaInicio = translateDate($arrFilterExtraVars['txt_fecha_init']);
    $sFechaFinal = translateDate($arrFilterExtraVars['txt_fecha_end']);

    $oAgentAnaliz = new paloSantoAgentAnaliz($pDB);
    //begin grid parameters
    
    $bIssabelNuevo = method_exists('paloSantoGrid', 'setURL');

    $oGrid = new paloSantoGrid($smarty);
    $oGrid->enableExport();   // enable export.
    $oGrid->showFilter($htmlFilter);

    // Obtain data
    // 1. Breaks data
    $reportsBreakData = $oAgentAnaliz->getReportsBreakData($sFechaInicio, $sFechaFinal);
    
    // 2. Calls data
    $fieldPat = array(
        'number'    =>  array(),
        'queue'     =>  array(),
        'type'      =>  array(),
    );
    
    // Filtreleri ayarla
    if ($arrFilterExtraVars['number'] != '')
        $fieldPat['number'][] = $arrFilterExtraVars['number'];
    if ($arrFilterExtraVars['queue'] != '')
        $fieldPat['queue'][] = $arrFilterExtraVars['queue'];
    if ($arrFilterExtraVars['type'] != '')
        $fieldPat['type'][] = $arrFilterExtraVars['type'];
    else
        $fieldPat['type'] = array('IN', 'OUT');
    
    $callsPerAgentData = $oAgentAnaliz->getCallsPerAgentData(
        $sFechaInicio.' 00:00:00',
        $sFechaFinal.' 23:59:59',
        $fieldPat
    );
    
    // 3. Login/Logout data (session time)
    $loginLogoutData = $oAgentAnaliz->getLoginLogoutData(
        $sFechaInicio.' 00:00:00',
        $sFechaFinal.' 23:59:59',
        (empty($arrFilterExtraVars['queue'])) ? NULL : $arrFilterExtraVars['queue']
    );
    
    // Merge the data to create analysis
    $arrData = $oAgentAnaliz->mergeAgentData($reportsBreakData, $callsPerAgentData, $loginLogoutData);
    
    // Create column headers
    $arrColumnas = array(
        _tr('Agent Number'),
        _tr('Agent Name'),
        _tr('Calls Answered'),
        _tr('Total Duration'),
        _tr('Average Duration'),
        _tr('Max Duration'),
        _tr('Session Time'),
        _tr('Total Break Time'),
        _tr('Efficiency (%)')
    );
    
    // Format data to display
    $formattedData = array();
    $bExportando = $bIssabelNuevo ? $oGrid->isExportAction() : 
        ((isset($_GET['exportcsv']) && $_GET['exportcsv'] == 'yes') || 
         (isset($_GET['exportspreadsheet']) && $_GET['exportspreadsheet'] == 'yes') || 
         (isset($_GET['exportpdf']) && $_GET['exportpdf'] == 'yes'));
    
    $sTagInicio = (!$bExportando) ? '<b>' : '';
    $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
    
    // Mesai süresi (09:00-18:00) - 9 saat = 32400 saniye
    $dailyWorkingHours = 9; // Günlük mesai saati
    $totalWorkingTimePerDay = $dailyWorkingHours * 60 * 60; // Günlük mesai süresi saniye cinsinden
    
    // Başlangıç ve bitiş tarihleri arasındaki gün sayısını hesapla
    $startDate = new DateTime($sFechaInicio);
    $endDate = new DateTime($sFechaFinal);
    $interval = $startDate->diff($endDate);
    $numDays = $interval->days + 1; // Başlangıç ve bitiş günleri dahil
    
    // Toplam mesai süresi (tüm günler için)
    $totalWorkingTime = $totalWorkingTimePerDay * $numDays;
    
    // Add totals row
    $totalRow = array(
        $sTagInicio._tr('Total').$sTagFinal,
        '',
        0, // Total calls
        0, // Total duration
        0, // Will calculate average
        0, // Will find max
        0, // Total session time
        0, // Total break time
        0  // Placeholder for efficiency
    );
    
    // Format and calculate totals
    foreach($arrData as $agent) {
        // Verisi olmayan ajanları atla
        if ($agent['num_answered'] <= 0 && $agent['session_time'] <= 0) {
            continue; // Bu ajanı sonuç listesine ekleme
        }
        
        // Calculate total break time for this agent
        $totalBreakTime = 0;
        foreach($agent['breaks'] as $idBreak => $breakDuration) {
            $totalBreakTime += $breakDuration;
        }
        
        $row = array(
            $agent['numero_agente'],
            $agent['nombre_agente'],
            $agent['num_answered'],
            formatoSegundos($agent['sum_duration']),
            formatoSegundos($agent['avg_duration']),
            formatoSegundos($agent['max_duration']),
            formatoSegundos($agent['session_time']),
            formatoSegundos($totalBreakTime),
        );
        
        // Calculate efficiency based on working hours (09:00-18:00)
        // Verimlilik = oturum süresi / toplam mesai süresi
        $efficiency = round(($agent['session_time'] / $totalWorkingTime) * 100, 2);
        // Verimlilik %100'den fazla olamaz
        if ($efficiency > 100) $efficiency = 100;
        
        $row[] = $efficiency . '%';
        
        // Update totals
        $totalRow[2] += $agent['num_answered'];
        $totalRow[3] += $agent['sum_duration'];
        $totalRow[6] += $agent['session_time']; // Add to total session time
        $totalRow[7] += $totalBreakTime; // Add to total break time
        if ($agent['max_duration'] > $totalRow[5]) $totalRow[5] = $agent['max_duration'];
        
        $formattedData[] = $row;
    }
    
    // Format total row
    $rawTotalCalls = $totalRow[2];
    $rawTotalDuration = $totalRow[3];
    $totalBreakSeconds = $totalRow[7];
    
    $totalRow[2] = $sTagInicio.$rawTotalCalls.$sTagFinal;
    $totalRow[3] = $sTagInicio.formatoSegundos($rawTotalDuration).$sTagFinal;
    
    // Ortalama süre hesaplaması
    $avgDuration = ($rawTotalCalls > 0) ? ($rawTotalDuration / $rawTotalCalls) : 0;
    $totalRow[4] = $sTagInicio.formatoSegundos($avgDuration).$sTagFinal;
    $totalRow[5] = $sTagInicio.formatoSegundos($totalRow[5]).$sTagFinal;
    
    // Toplam mola süresi
    $totalRow[6] = $sTagInicio.formatoSegundos($totalRow[6]).$sTagFinal;
    
    // Toplam mola süresi
    $totalRow[7] = $sTagInicio.formatoSegundos($totalBreakSeconds).$sTagFinal;
    
    // Verimlilik hesaplaması - Mesai saatlerine göre (09:00-18:00)
    // Tüm ajanlar için toplam mesai süresi = filtrelenmiş ajanların sayısı * günlük mesai süresi
    $numAgents = count($formattedData) - 1; // -1 for total row
    $totalExpectedWorkTime = $numAgents * $totalWorkingTime;
    
    // Toplam verimlilik = toplam oturum süresi / toplam mesai süresi
    $efficiency = ($totalExpectedWorkTime > 0) ? 
        round(($totalRow[6] / $totalExpectedWorkTime) * 100, 2) : 0;
    
    // Verimlilik %100'den fazla olamaz
    if ($efficiency > 100) $efficiency = 100;
    
    $totalRow[8] = $sTagInicio.$efficiency.'%'.$sTagFinal;
    
    // Add total row
    $formattedData[] = $totalRow;
    
    // Render grid
    if ($bIssabelNuevo) {
        $url = construirURL($arrFilterExtraVars);
        $oGrid->setURL($url);
        $oGrid->setData($formattedData);
        $oGrid->setColumns($arrColumnas);
        $oGrid->setTitle(_tr("Agent Performance Analysis"));
        $oGrid->pagingShow(false); 
        $oGrid->setNameFile_Export(_tr("Agent Performance Analysis"));
     
        $smarty->assign("SHOW", _tr("Show"));
        return $oGrid->fetchGrid();
    } else {
        $url = construirURL($arrFilterExtraVars);
        $offset = 0;
        $total = count($formattedData);
        $limit = $total;

        function _map_name($s) { return array('name' => $s); }
        $arrGrid = array("title"    =>  _tr('Agent Performance Analysis'),
                "url"      => $url,
                "icon"     => "images/list.png",
                "width"    => "99%",
                "start"    => ($total==0) ? 0 : $offset + 1,
                "end"      => ($offset+$limit)<=$total ? $offset+$limit : $total,
                "total"    => $total,
                "columns"  => array_map('_map_name', $arrColumnas),
                );
        if (isset($_GET['exportpdf']) && $_GET['exportpdf'] == 'yes' && method_exists($oGrid, 'fetchGridPDF')) {
            // PDF çıktısı almadan önce tamponlamayı temizle
            ob_end_clean();
            return $oGrid->fetchGridPDF($arrGrid, $formattedData);
        }
        if (isset($_GET['exportspreadsheet']) && $_GET['exportspreadsheet'] == 'yes' && method_exists($oGrid, 'fetchGridXLS'))
            return $oGrid->fetchGridXLS($arrGrid, $formattedData);
        if ($bExportando) {
            $title = $sFechaInicio."-".$sFechaFinal;
            header("Cache-Control: private");
            header("Pragma: cache");
            header('Content-Type: text/csv; charset=utf-8; header=present');
            header("Content-disposition: attachment; filename=\"".$title.".csv\"");
        }
        if ($bExportando)
            return $oGrid->fetchGridCSV($arrGrid, $formattedData);
        $sContenido = $oGrid->fetchGrid($arrGrid, $formattedData);
        if (strpos($sContenido, '<form') === FALSE)
            $sContenido = "<form  method=\"POST\" style=\"margin-bottom:0;\" action=\"$url\">$sContenido</form>";
        return $sContenido;
    }
}

function createFieldFilter($arrDataTipo)
{
    $arrFormElements = array(
        "txt_fecha_init"  => array(
            "LABEL"                     => _tr('Start Date'),
            "REQUIRED"                  => "yes",
            "INPUT_TYPE"                => "DATE",
            "INPUT_EXTRA_PARAM"         => "",
            "VALIDATION_TYPE"           => "ereg",
            "VALIDATION_EXTRA_PARAM"    => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"
        ),
        "txt_fecha_end"  => array(
            "LABEL"                     => _tr('End Date'),
            "REQUIRED"                  => "yes",
            "INPUT_TYPE"                => "DATE",
            "INPUT_EXTRA_PARAM"         => "",
            "VALIDATION_TYPE"           => "ereg",
            "VALIDATION_EXTRA_PARAM"    => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"
        ),
        "type" => array(
            "LABEL"                  => _tr("Type"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataTipo,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "^(IN|OUT)$"),
        "queue" => array(
            "LABEL"                  => _tr("Queue"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
        "number" => array(
            "LABEL"                  => _tr("No.Agent"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
    );
    return $arrFormElements;
}

function getAction()
{
    if(getParameter("show")) //Get parameter by POST (submit)
        return "show";
    else if(getParameter("filter"))
        return "filter";
    else if(getParameter("submit_export"))
        return "export";
    else
        return "report";
}

function formatoSegundos($iSeg)
{
    $iSeg = (int)$iSeg;
    $iHora = $iMinutos = $iSegundos = 0;
    $iSegundos = $iSeg % 60; $iSeg = ($iSeg - $iSegundos) / 60;
    $iMinutos = $iSeg % 60; $iSeg = ($iSeg - $iMinutos) / 60;
    $iHora = $iSeg;
    return sprintf('%02d:%02d:%02d', $iHora, $iMinutos, $iSegundos);
}
?> 