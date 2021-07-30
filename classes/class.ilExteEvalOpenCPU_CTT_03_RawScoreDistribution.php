<?php

/**
 * Provides information about the raw score distribution (skewness, kurtosis, density plot)
 */
class ilExteEvalOpenCPU_CTT_03_RawScoreDistribution extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPURawScoreDistribution';

	/**
	 * Calculate the raw score distribution
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		require_once('utility/class.ilExteEvalOpenCPU.php');
				
		$details = new ilExteStatDetails();
		
		$plugin = new ilExtendedTestStatisticsPlugin;
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU");
		$server = $config['server'];
		
		$list = '';
		foreach ($this->data->getAllParticipants() as $participant)
		{
			$list .= $participant->current_reached_points . ',';
		}
		$list = rtrim($list, ',');

		$path = "/ocpu/library/base/R/identity";
		$query["x"] = 	"data <- c({$list});" .
						'library(moments);' .
						'skewness <- skewness(data);' .
						'kurtosis <- kurtosis(data);' .
						'library(ggplot2);' .
						'datasim <- data.frame(data);' .
						'ggplot(datasim, aes(x = data)) + ' .
						'geom_density(aes(y = ..count..), colour = "blue") + xlab(expression(bold("Raw Score"))) +  ' .
						'geom_histogram(fill = "black", binwidth = 0.5, alpha = 0.5) + ' .
						'ylab(expression(bold("Count"))) + theme_bw()';

		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);		
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		$needles = array('skewness', 'kurtosis', 'graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$skewness = json_decode(stripslashes($results['skewness']),TRUE);
		$kurtosis = json_decode(stripslashes($results['kurtosis']),TRUE);
		$plots = $results['graphics'];

		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		
		//show raw score distribution
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPURawScoreDistribution_Plot'));
		$template->setVariable('PLOT', "<img src='data:image/png;base64," . $plots[0] . "'>");
		$template->parseCurrentBlock("accordion_plot");
		
		$details->customHTML = $template->get();

		// raw score details
		$details->columns = array (
				ilExteStatColumn::_create('skewness', $this->plugin->txt('tst_OpenCPURawScoreDistribution_skewness'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('kurtosis', $this->plugin->txt('tst_OpenCPURawScoreDistribution_kurtosis'),ilExteStatColumn::SORT_NUMBER)
		);
		$details->rows[] = array(
				'skewness' => ilExteStatValue::_create($skewness[0], ilExteStatValue::TYPE_NUMBER, 3),
				'kurtosis' => ilExteStatValue::_create($kurtosis[0], ilExteStatValue::TYPE_NUMBER, 3),
				
		);
		
		return $details;
	}
}