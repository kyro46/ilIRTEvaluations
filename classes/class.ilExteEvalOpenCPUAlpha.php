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
	protected $provides_details = true;
	
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
		

		$result = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);//Format: {\\"alpha\\":[x.xxx]}\n
		$serialized = json_decode(substr(stripslashes($result), 2, -3),TRUE);

		$value->value = $serialized[alpha][0];
		return $value;
	}
	
	/**
	 * Calculate and classify alpha per removed item
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		$details = new ilExteStatDetails();
		
		$plugin = new ilExtendedTestStatisticsPlugin;
		$data = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $data['server'];
		
		$csv = ilExteEvalOpenCPU::getBasicData($this);
		
		$path = "/ocpu/library/base/R/identity/json";
		$query["x"] = "library(ltm); data <- read.csv(text='{$csv}', row.names = 1, header= TRUE); result <- descript(data); library(jsonlite); toJSON(result[11])";
		
		
		$result = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		$serialized = json_decode(substr(stripslashes($result), 2, -3),TRUE);

		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id','',ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title','',ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('alpha_if_removed','',ilExteStatColumn::SORT_NUMBER)
		);
		
		//pupulate rows
		$i = 1; //because $serialized[alpha][0] contains the overall alpha
		foreach ($this->data->getAllQuestions() as $question)
		{
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'alpha_if_removed' => ilExteStatValue::_create($serialized[alpha][$i][0], ilExteStatValue::TYPE_NUMBER, 3)
			);
			$i++;
		}
		
		return $details;
	}
}