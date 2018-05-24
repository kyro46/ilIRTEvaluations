<?php

/**
 * Calculates Rasch-parameter (contrained/not constrained) via OpenCPU
 * TODO Handle questions removed due to zero variance
 * TODO Restructure to insert a NA-row of type text instead of 0
 * TODO Gives an evaluation of the model-fit
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
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $config['server'];
		
		$data = ilExteEvalOpenCPU::getBasicData($this, TRUE); //TRUE -> dichotomize at 50% of reachable points
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
			"library(ltm);" .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"fit_constrained <- rasch(data, constraint = cbind(length(data)+1,1)); " . 	//constrained
			"fit_unconstrained <- rasch(data); " . 										//unconstrained
			"coef_constrained <- coef(fit_constrained);" .
			"coef_unconstrained <- coef(fit_unconstrained);" .
			'op <- par(mfrow = c(2, 2));' .
			'plot(fit_unconstrained, lwd = 2, legend = TRUE, ncol = 2); par(op);' .
			'plot(fit_unconstrained, type = "IIC", legend = TRUE, cx = "topright", lwd = 2, cex = 1.4);' .
			'plot(fit_unconstrained, type = "IIC", items = 0, lwd = 2);';
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('coef_constrained','coef_unconstrained','graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$serialized_constrained = json_decode(stripslashes($results['coef_constrained']),TRUE);
		$serialized_unconstrained = json_decode(stripslashes($results['coef_unconstrained']),TRUE);
		$plots = $results['graphics'];
		
		//prepare and create output of plots
		$customHTML = ilExteEvalOpenCPU::getPlotAccordionHTML($this, $plots);
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('rasch_difficulty', $this->plugin->txt('tst_OpenCPUdichotomous_table_RaschDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('rasch_disc', $this->plugin->txt('tst_OpenCPUdichotomous_table_RaschDisc'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_difficulty', $this->plugin->txt('tst_OpenCPUdichotomous_table_1PLDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_disc', $this->plugin->txt('tst_OpenCPUdichotomous_table_1PLDisc'), ilExteStatColumn::SORT_NUMBER)
		);
		
		//rows
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