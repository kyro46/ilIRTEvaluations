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
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU_Basedata");
		$server = $config['server'];
		
		$list = '';
		foreach ($this->data->getAllParticipants() as $participant)
		{
			$list .= $participant->current_reached_points . ',';
		}
		$list = rtrim($list, ',');

		$path = "/ocpu/library/base/R/identity";
		$query["x"] = 	"data <- c({$list});" .
						'library(psych);' .
						'description <- describe(data);' .
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
		
		$needles = array('description', 'graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$description = json_decode(substr(stripslashes($results['description']), 2, -3),TRUE);
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
				ilExteStatColumn::_create('n', $this->plugin->txt('tst_OpenCPURawScoreDistribution_n'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('mean', $this->plugin->txt('tst_OpenCPURawScoreDistribution_mean'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('sd', $this->plugin->txt('tst_OpenCPURawScoreDistribution_sd'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('median', $this->plugin->txt('tst_OpenCPURawScoreDistribution_median'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('trimmed', $this->plugin->txt('tst_OpenCPURawScoreDistribution_trimmed'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('mad', $this->plugin->txt('tst_OpenCPURawScoreDistribution_mad'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('min', $this->plugin->txt('tst_OpenCPURawScoreDistribution_min'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('max', $this->plugin->txt('tst_OpenCPURawScoreDistribution_max'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('range', $this->plugin->txt('tst_OpenCPURawScoreDistribution_range'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('skew', $this->plugin->txt('tst_OpenCPURawScoreDistribution_skewness'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('kurtosis', $this->plugin->txt('tst_OpenCPURawScoreDistribution_kurtosis'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('se', $this->plugin->txt('tst_OpenCPURawScoreDistribution_se'),ilExteStatColumn::SORT_NUMBER)
		);
		$details->rows[] = array(
				'n' => ilExteStatValue::_create($description['n'], ilExteStatValue::TYPE_NUMBER, 0),
				'mean' => ilExteStatValue::_create($description['mean'], ilExteStatValue::TYPE_NUMBER, 2),
				'sd' => ilExteStatValue::_create($description['sd'], ilExteStatValue::TYPE_NUMBER, 2),
				'median' => ilExteStatValue::_create($description['median'], ilExteStatValue::TYPE_NUMBER, 2),
				//'trimmed' => ilExteStatValue::_create($description['trimmed'], ilExteStatValue::TYPE_NUMBER, 2),
				//'mad' => ilExteStatValue::_create($description['mad'], ilExteStatValue::TYPE_NUMBER, 2),
				'min' => ilExteStatValue::_create($description['min'], ilExteStatValue::TYPE_NUMBER, 2),
				'max' => ilExteStatValue::_create($description['max'], ilExteStatValue::TYPE_NUMBER, 2),
				'range' => ilExteStatValue::_create($description['range'], ilExteStatValue::TYPE_NUMBER, 2),
				'skew' => ilExteStatValue::_create($description['skew'], ilExteStatValue::TYPE_NUMBER, 2),
				'kurtosis' => ilExteStatValue::_create($description['kurtosis'], ilExteStatValue::TYPE_NUMBER, 2),
				'se' => ilExteStatValue::_create($description['se'], ilExteStatValue::TYPE_NUMBER, 2)				
		);
		
		return $details;
	}
}