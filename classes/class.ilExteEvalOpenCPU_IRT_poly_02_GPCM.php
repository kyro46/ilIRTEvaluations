<?php

/**
 * Calculates GPCM-parameters via OpenCPU
 * TODO restructure to insert a NA-row of type text instead of 0
 * TODO Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPU_IRT_poly_02_GPCM extends ilExteEvalTest
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
		
		// check minimum number of participants
		$number_of_users = count($this->data->getAllParticipants());
		if ($number_of_users < 2)
		{
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_calculation_error');
			return $details;
		}

		$plugin = new ilExtendedTestStatisticsPlugin;
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $config['server'];
		
		$data = ilExteEvalOpenCPU::getBasicData($this);
		$columnsLegend = intdiv(count($this->data->getAllQuestions()),10);
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
			"library(ltm);" .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"fit <- gpcm(data); " . //unconstrained, options: rasch, 1PL, gpcm (default)
			"coef <- coef(fit);" .
			'op <- par(mfrow = c(2, 2));' .
			'plot(fit, lwd = 2, legend = TRUE, ncol = 2); par(op);' .
			"plot(fit, type = 'IIC', col = rainbow(40, start = 0, end = 1)," .
			"legend = TRUE, cx = 'topright', lwd = 2, cex = 1, ncol = {$columnsLegend});" .
			'plot(fit, type = "IIC", items = 0, lwd = 2);';
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;			
		}
		
		//prepare results
		$needles = array('coef','graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$serialized = json_decode(stripslashes($results['coef']),TRUE);
		$plots = $results['graphics'];
		
		//prepare and create output of plots
		$customHTML = ilExteEvalOpenCPU::getPlotAccordionHTML($this, $plots);
		$details->customHTML = $customHTML;
		
		//create and populate table
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('gpcm_difficulty_mean', $this->plugin->txt('tst_OpenCPUPolytomousGPCM_table_Diff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('gpcm_disc', $this->plugin->txt('tst_OpenCPUPolytomousGPCM_table_Disc'), ilExteStatColumn::SORT_NUMBER)
		);

		//rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			if(!$data['dichotomous']) {
				//Questions in response can be != questions in test due to removing questions with 0 variance!
				if(in_array($question->question_id,$data['removed'])){
					$serialized['X'.$question->question_id] = array(0, 0);
				}
				//calculate mean difficulty according to proposal 1 from [Usama, Chang, Anderson, 2015, DOI:10.1002/ets2.12065]
				$disc = array_slice($serialized['X'.$question->question_id], -1);
				$sum = array_sum($serialized['X'.$question->question_id]) - $disc[0];
				$mean = $sum / (count($serialized['X'.$question->question_id])-1);
			} else {
				if(in_array($question->question_id,$data['removed'])){
					$disc[0] = 0;
					$mean = 0;
				} else {
					$disc[0] = $serialized[$i][1];
					$mean = $serialized[$i][0];
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