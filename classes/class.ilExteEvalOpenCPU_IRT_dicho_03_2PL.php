<?php

/**
 * Calculates 2-PL-Model via OpenCPU
 * TODO Handle questions removed due to zero variance
 * TODO Restructure to insert a NA-row of type text instead of 0
 * TODO Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPU_IRT_dicho_03_2PL extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPU2PL';
	
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
		
		$data = ilExteEvalOpenCPU::getBasicData($this, TRUE); //TRUE -> dichotomize at 50% of reachable points
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
		"library(ltm);" .
		"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
		"fit_ltm <- ltm(data ~ z1); " .
		"coef_ltm <- coef(fit_ltm);" .
		'op <- par(mfrow = c(2, 2));' .
		'plot(fit_ltm, lwd = 2, legend = TRUE, ncol = 2); par(op);' .
		'plot(fit_ltm, type = "IIC", legend = TRUE, cx = "topright", lwd = 2, cex = 1.4);' .
		'plot(fit_ltm, type = "IIC", items = 0, lwd = 2);';
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('coef_ltm','graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$serialized = json_decode(stripslashes($results['coef_ltm']),TRUE);
		$plots = $results['graphics'];
		
		//prepare and create output of plots
		$customHTML = ilExteEvalOpenCPU::getPlotAccordionHTML($this, $plots);
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('2PL_difficulty', $this->plugin->txt('tst_OpenCPU2PL_table_2PLDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('2PL_disc', $this->plugin->txt('tst_OpenCPU2PL_table_2PLDisc'), ilExteStatColumn::SORT_NUMBER)
		);
		
		//rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'2PL_difficulty' => ilExteStatValue::_create($serialized[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'2PL_disc' => ilExteStatValue::_create($serialized[$i][1], ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator)
			);
			$i++;
		}
		
		return $details;
	}
}