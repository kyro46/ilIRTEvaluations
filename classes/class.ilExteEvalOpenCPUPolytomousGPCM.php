<?php

/**
 * Calculates GPCM-parameters via OpenCPU
 * TODO restructure to insert a NA-row of type text instead of 0
 * TODO Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPUPolytomousGPCM extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGPCM';
	
	/**
	 * Calculate and classify alpha per removed item
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		$details = new ilExteStatDetails();
		
		$plugin = new ilExtendedTestStatisticsPlugin;
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $config['server'];
		
		$data = ilExteEvalOpenCPU::getBasicData($this);
		
		$path = "/ocpu/library/base/R/identity/json";

		$query_gpcm["x"] = 	"library(ltm);" .
				"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
				"gpcm <- gpcm(data); " . //unconstrained, options: rasch, 1PL, gpcm (default)
				"coef <- coef(gpcm);" .
				"library(jsonlite);" .
				"toJSON(coef)";
		
		$result_gpcm = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query_gpcm);
		
		$result_gpcm == NULL ? $serialized_gpcm = array() : $serialized_gpcm = json_decode(substr(stripslashes($result_gpcm), 2, -3),TRUE);
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPUAlpha_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPUAlpha_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('gpcm_difficulty_mean', $this->plugin->txt('tst_OpenCPUPolytomousGPCM_table_Diff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('gpcm_disc', $this->plugin->txt('tst_OpenCPUPolytomousGPCM_table_Disc'), ilExteStatColumn::SORT_NUMBER)
		);

		//pupulate rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			if(!$data['dichotomous']) {
				//Questions in response can be != questions in test due to removing questions with 0 variance!
				if(in_array($question->question_id,$data['removed'])){
					$serialized_gpcm[X.$question->question_id] = array(0, 0);
				}
				//calculate mean difficulty according to proposal 1 from [Usama, Chang, Anderson, 2015, DOI:10.1002/ets2.12065]
				$disc = array_slice($serialized_gpcm['X'.$question->question_id], -1);
				$sum = array_sum($serialized_gpcm['X'.$question->question_id]) - $disc[0];
				$mean = $sum / (count($serialized_gpcm['X'.$question->question_id])-1);
			} else {
				if(in_array($question->question_id,$data['removed'])){
					$disc[0] = 0;
					$mean = 0;
				} else {
					$disc[0] = $serialized_gpcm[$i][1];
					$mean = $serialized_gpcm[$i][0];
				}				
				$i++;
			}

			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'gpcm_difficulty_mean' => ilExteStatValue::_create($mean, ilExteStatValue::TYPE_NUMBER, 3),
					'gpcm_disc' => ilExteStatValue::_create($disc[0], ilExteStatValue::TYPE_NUMBER, 3)
			);
		}
		
		return $details;
	}
}