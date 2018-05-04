<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Calculates Cronbach Alpha via OpenCPU
 */
class ilExteEvalOpenCPUAlpha extends ilExteEvalTest
{
	/**
	 * @var bool	evaluation provides a single value for the overview level
	 */
	protected $provides_value = true;
	
	/**
	 * @var bool	evaluation provides data for a details screen
	 */
	protected $provides_details = false;
	
	/**
	 * @var array list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
	 */
	protected $allowed_test_types = array();
	
	/**
	 * @var array	list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected $allowed_question_types = array();
	
	/**
	 * @var string	specific prefix of language variables (lowercase classname is default)
	 */
	protected $lang_prefix = 'tst_OpenCPUAlpha';

	
	/**
	 * Calculate and get the single value for a test
	 *
	 * @return ilExteStatValue
	 */
	public function calculateValue()
	{
		$value = new ilExteStatValue;
		$value->type = ilExteStatValue::TYPE_NUMBER;
		$value->precision = 2;
		$value->value = null;
		
		$plugin = new ilExtendedTestStatisticsPlugin;
		$data = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $data['server'];

		$csv = ilExteEvalOpenCPU::getBasicData($this);
		
		$path = "/ocpu/library/base/R/identity/json";
		$query["x"] = "library(ltm); data <- read.csv(text='{$csv}', row.names = 1, header= TRUE); result <- cronbach.alpha(data); library(jsonlite); toJSON(result[1])";
		

		//Format: {\\"alpha\\":[x.xxx]}\n
		$result = ilExteEvalOpenCPU::callOpenCPU($query, $path, $server);
		$object = json_decode(substr(stripslashes($result), 2, -3),TRUE);

		$value->value = $object[alpha][0];
		return $value;
	}
}