<?php

/**
 * Calculates GRM-parameters via OpenCPU
 * Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPUPolytomousGRM extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGRM';

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

		$csv = ilExteEvalOpenCPU::getBasicData($this, FALSE, FALSE, TRUE); //TRUE -> repack data for calculations in package ltm
	
		$path = "/ocpu/library/base/R/identity/json";
		/*
		$query_constrained["x"] = 	"library(ltm);" .
				"data <- read.csv(text='{$csv}', row.names = 1, header= TRUE);" .
				"grm <- grm(data, constrained = TRUE); " . //constrained
				"coef <- coef(grm);" .
				"library(jsonlite);" .
				"toJSON(coef)";
		*/
		$query_unconstrained["x"] = 	"library(ltm);" .
				"data <- read.csv(text='{$csv}', row.names = 1, header= TRUE);" .
				"grm <- grm(data); " . //unconstrained
				"coef <- coef(grm);" .
				"library(jsonlite);" .
				"toJSON(coef)";
		
		//$result_constrained = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query_constrained);
		$result_unconstrained = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query_unconstrained);

		//$serialized_constrained = json_decode(substr(stripslashes($result_constrained), 2, -3),TRUE);
		$serialized_unconstrained = json_decode(substr(stripslashes($result_unconstrained), 2, -3),TRUE);
		
		
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPUAlpha_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPUAlpha_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_difficulty_mean', $this->plugin->txt('tst_OpenCPUPolytomousGRM_table_Diff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_disc', $this->plugin->txt('tst_OpenCPUPolytomousGRM_table_Disc'), ilExteStatColumn::SORT_NUMBER)
		);
		
		//pupulate rows
		foreach ($this->data->getAllQuestions() as $question)
		{
			//Questions in response can be != questions in test due to removing questions with 0 variance!
			if(!array_key_exists('X'. $question->question_id,$serialized_unconstrained)){
				$serialized_unconstrained[X.$question->question_id] = array(0, 0);
			}
			
			//calculate mean difficulty according to proposal 1 from [Usama, Chang, Anderson, 2015, DOI:10.1002/ets2.12065]
			$disc = array_slice($serialized_unconstrained['X'.$question->question_id], -1);
			$sum = array_sum($serialized_unconstrained['X'.$question->question_id]) - $disc[0];
			$mean = $sum / (count($serialized_unconstrained['X'.$question->question_id])-1);

			error_log(print_r("SUMME:  " . array_sum($serialized_unconstrained['X'.$question->question_id]), TRUE));
			error_log(print_r("SUMME2: " . (array_sum($serialized_unconstrained['X'.$question->question_id]) - $disc[0]), TRUE));
			error_log(print_r("SUMME3: " . $sum, TRUE));
			
			
			
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'grm_difficulty_mean' => ilExteStatValue::_create($mean, ilExteStatValue::TYPE_NUMBER, 3),
					'grm_disc' => ilExteStatValue::_create($disc[0], ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator)
			);
			$i++;
		}
		
		return $details;
	}
}