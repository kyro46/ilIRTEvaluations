<?php

/**
 * Displays the basic matrix for IRT calculations which is transmitted to OpenCPU
 * Informs about the removed items due to zero variance
 * Provides the configuration parameter for the OpenCPU server address
 */
class ilExteEvalOpenCPU_Basedata extends ilExteEvalTest
{
	/**
	 * @var bool	evaluation provides a single value for the overview level
	 */
	protected $provides_value = false;

	/**
	 * @var bool	evaluation provides data for a details screen
	 */
	protected $provides_details = true;

	/**
	 * @var bool    evaluation provides custom HTML
	 */
	protected $provides_HTML = true;

	/**
	 * @var array	list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
	 */
	protected $allowed_test_types = array(self::TEST_TYPE_FIXED);

	/**
	 * @var array	list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected $allowed_question_types = array();

	/**
	 * @var string	specific prefix of language variables (lowercase classname is default)
	 */
	protected $lang_prefix = 'tst_OpenCPU_BaseData';

	/**
	 * Get the source data
	 * Needed to pass the protected member to the utility class
	 *
	 * @return ilExteStatSourceData
	 */
	protected function getData() {
		return $this->data;
	}
	
	/**
	 * Get the available parameters for this evaluation
	 * @return ilExteStatParam
	 */
	public function getAvailableParams()
	{
		return array(
				ilExteStatParam::_create('server', ilExteStatParam::TYPE_STRING, 'https://cloud.opencpu.org'),
				ilExteStatParam::_create('dichotomization', ilExteStatParam::TYPE_STRING, 'half'),
		        ilExteStatParam::_create('show_R_code', ilExteStatParam::TYPE_BOOLEAN, FALSE),
		);
	}

	/**
	 * Show the raw answer matrix for OpenCPU
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		require_once('utility/class.ilExteEvalOpenCPU.php');
		
        $details = new ilExteStatDetails();
        $data = ilExteEvalOpenCPU::getBasicData($this->getData());
        
        $csv_string = $data['csv'];
        // code from https://www.php.net/manual/de/function.str-getcsv.php
        $data_array = str_getcsv($csv_string, "\n"); //parse the rows
        foreach($data_array as &$csv_row) $csv_row = str_getcsv($csv_row, ","); //parse the items in rows 
       
        // list removed items for user
        if (!empty($data['removed'])) {
            $template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Text.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
	        $template->setCurrentBlock("paragraph");
	        $template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPU_Basedata_removedItems'));
	        $template->setVariable('TEXT',  implode(",", $data['removed']));
	        $template->parseCurrentBlock("paragraph");
	        
	        //prepare and create output of custom HTML
	        $customHTML = $template->get();
	        $details->customHTML = $customHTML;
        }

        // columns
        $details->columns = array (
        	ilExteStatColumn::_create('active_id','ID',ilExteStatColumn::SORT_NUMBER),
        );

        for ($j = 1; $j < sizeof($data_array[0]); $j++)
        {
            array_push($details->columns, ilExteStatColumn::_create($data_array[0][$j],$data_array[0][$j],ilExteStatColumn::SORT_NUMBER));
        }
        
        // rows
        for ($i = 1; $i< sizeof($data_array); $i++) {
        	$details->rows[] = array(
        			'active_id' => ilExteStatValue::_create($data_array[$i][0], ilExteStatValue::TYPE_NUMBER, 0),
        	);
        	
        	for($j = 1; $j < sizeof($data_array[0]);$j++) {
        		$details->rows[count($details->rows)-1][$data_array[0][$j]] = ilExteStatValue::_create($data_array[$i][$j], ilExteStatValue::TYPE_NUMBER, 2);
        	}
        }
        return $details;
	}
}