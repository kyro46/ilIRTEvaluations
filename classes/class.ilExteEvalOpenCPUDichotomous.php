<?php

/**
 * Calculates Rasch/1PL-parameter (contrained/not constrained) via OpenCPU
 * ToDo Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPUDichotomous extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUDichotomous';
	
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
		
		$csv = ilExteEvalOpenCPU::getBasicData($this, TRUE); //TRUE -> dichotomize at 50% of reachable points

		$path = "/ocpu/library/base/R/identity/json";
		$query_constrained["x"] = 	"library(ltm);" .
				"data <- read.csv(text='{$csv}', row.names = 1, header= TRUE);" .
				"rasch <- rasch(data, constraint = cbind(length(data)+1,1)); " . //constrained
				"coef <- coef(rasch);" .
				"library(jsonlite);" .
				"toJSON(coef)";
		
		$query_unconstrained["x"] = 	"library(ltm);" .
				"data <- read.csv(text='{$csv}', row.names = 1, header= TRUE);" .
				"rasch <- rasch(data); " . //unconstrained
				"coef <- coef(rasch);" .
				"library(jsonlite);" .
				"toJSON(coef)";
		
		$result_constrained = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query_constrained);
		$result_unconstrained = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query_unconstrained);
		
		$serialized_constrained = json_decode(substr(stripslashes($result_constrained), 2, -3),TRUE);
		$serialized_unconstrained = json_decode(substr(stripslashes($result_unconstrained), 2, -3),TRUE);
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPUAlpha_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPUAlpha_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('rasch_difficulty', $this->plugin->txt('tst_OpenCPUdichotomous_table_RaschDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('rasch_disc', $this->plugin->txt('tst_OpenCPUdichotomous_table_RaschDisc'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_difficulty', $this->plugin->txt('tst_OpenCPUdichotomous_table_1PLDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_disc', $this->plugin->txt('tst_OpenCPUdichotomous_table_1PLDisc'), ilExteStatColumn::SORT_NUMBER)
		);
		
		//pupulate rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'rasch_difficulty' => ilExteStatValue::_create($serialized_constrained[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'rasch_disc' => ilExteStatValue::_create($serialized_constrained[$i][1], ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator),
					'1PL_difficulty' => ilExteStatValue::_create($serialized_unconstrained[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'1PL_disc' => ilExteStatValue::_create($serialized_unconstrained[$i][1], ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator)					
			);
			$i++;
		}
		
		return $details;
	}
}