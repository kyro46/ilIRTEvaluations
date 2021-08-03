<?php

/**
 * Calculates GRM-parameters via OpenCPU and MIRT
 * Calculate difficulty mean for polytomous items to provide a single comparable value
 */
class ilExteEvalOpenCPU_IRT_poly_01_GRM_01_parameter_MIRT extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGRM_parameter';
	
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
	 * Calculate parameters for the GRM via MIRT
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
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU_Basedata");
		$server = $config['server'];
		
		$data = ilExteEvalOpenCPU::getBasicData($this->getData());
		$columnsLegend = intdiv(count($this->data->getAllQuestions()),10);
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
			"library(mirt);" .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"fit <- mirt(data, 1, itemtype='graded');" .
			"coef <- coef(fit);" .
			"plot_trace <- plot(fit, type = 'trace');" .
			"plot_infoSE <- plot(fit, type = 'infoSE');" .
			"plot_info <- plot(fit, type = 'info');" .
			"plot_SE <- plot(fit, type = 'SE');" .
			"plot_expected_score <- plot(fit);";
			
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('coef','plot_trace','plot_infoSE','plot_info','plot_SE','plot_expected_score' );
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		
		$serialized = json_decode(stripslashes($results['coef']),TRUE);
		
		// create accordions for plots and textual summaries
		// Expected Score
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_parameter_acc_expTotalScore'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," . $results['plot_expected_score'] . "'>";
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");

		// Testinformation + standard error
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_parameter_acc_TestinformationError'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," . $results['plot_infoSE'] . "'>";
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		// Tracelines
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_parameter_acc_ICC'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," . $results['plot_trace'] . "'>";
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		//prepare and create output of plots
		$customHTML = $template->get();
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_difficulty_mean', $this->plugin->txt('tst_OpenCPUPolytomousGRM_table_Diff'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_disc', $this->plugin->txt('tst_OpenCPUPolytomousGRM_table_Disc'), ilExteStatColumn::SORT_NUMBER),
		);
		
		//rows
		foreach ($this->data->getAllQuestions() as $question)
		{
			
			if(!$data['dichotomous']) {
				//Questions in response can be != questions in test due to removing questions with 0 variance!
				if(!array_key_exists('X'.$question->question_id,$serialized)){
					$serialized['X'.$question->question_id][0] = array(0, 0);
				}
				
				//calculate mean difficulty according to proposal 1 from [Usama, Chang, Anderson, 2015, DOI:10.1002/ets2.12065]
				$disc[0] = $serialized['X' . $question->question_id][0][0];
				$sum = array_sum($serialized['X'.$question->question_id][0]) - $disc[0];
				$mean = $sum / (count($serialized['X'.$question->question_id][0])-1);
			} else {
				if(in_array($question->question_id,$data['removed'])){
					$disc[0] = 0;
					$mean = 0;
				} else {
					$disc[0] = $serialized['X' . $question->question_id][0][0];
					$mean = $serialized['X' . $question->question_id][0][1];
				}
				$i++;
			}
			
			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'grm_difficulty_mean' => ilExteStatValue::_create($mean, ilExteStatValue::TYPE_NUMBER, 3),
					'grm_disc' => ilExteStatValue::_create($disc[0], ilExteStatValue::TYPE_NUMBER, 3, NULL),
					
			);
		}
		
		return $details;
	}
}