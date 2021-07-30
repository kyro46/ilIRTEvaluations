<?php

/**
 * Calculates 1PL-parameter via OpenCPU
 * TODO Handle questions removed due to zero variance
 */
class ilExteEvalOpenCPU_IRT_dicho_02_1PL extends ilExteEvalTest
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
	protected $allowed_test_types = array();
	
	/**
	 * @var array	list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected $allowed_question_types = array();
	
	/**
	 * @var string	specific prefix of language variables (lowercase classname is default)
	 */
	protected $lang_prefix = 'tst_OpenCPU1PL';
	
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
	 * Calculate parameters for the 1-PL-Model (common discrimination not 1 for all items)
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		require_once('utility/class.ilExteEvalOpenCPU.php');
		
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
		
		$data = ilExteEvalOpenCPU::getBasicData($this->getData(), TRUE); //TRUE -> dichotomize at 50% of reachable points
		$columnsLegend = intdiv(count($this->data->getAllQuestions()),10);
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
			"library(ltm);" .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"fit <- rasch(data); " . 										//unconstrained
			"coef <- coef(fit);" .
			"plot(fit, type = 'ICC', col = rainbow(40, start = 0, end = 1)," .
			"legend = TRUE, cx = 'topright', lwd = 2, cex = 1, ncol = {$columnsLegend});" .
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
		$customHTML = ilExteEvalOpenCPU::getPlotAccordionHTML($this->plugin, $plots);
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_difficulty', $this->plugin->txt('tst_OpenCPU1PL_table_1PLDiff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('1PL_disc', $this->plugin->txt('tst_OpenCPU1PL_table_1PLDisc'), ilExteStatColumn::SORT_NUMBER)
		);
		
		//rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'1PL_difficulty' => ilExteStatValue::_create($serialized[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'1PL_disc' => ilExteStatValue::_create($serialized[$i][1], ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator)					
			);
			$i++;
		}
		
		return $details;
	}
}