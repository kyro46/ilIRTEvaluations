<?php

/**
 * Provides a factor analysis via OpenCPU
 */
class ilExteEvalOpenCPU_CTT_02_FactorAnalysis extends ilExteEvalTest
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
	protected $allowed_test_types = array(self::TEST_TYPE_FIXED);
	
	/**
	 * @var array	list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected $allowed_question_types = array();
	
	/**
	 * @var string	specific prefix of language variables (lowercase classname is default)
	 */
	protected $lang_prefix = 'tst_OpenCPUFactorAnalysis';

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
	 * Calculate and plot factor analysis
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
		
		$path = "/ocpu/library/base/R/identity";
		$query["x"] = 'library(psych);' .
			'library(GPArotation);' .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			'factors <- fa.parallel(data, fa = "fa");' .
			'nrfactors <- factors$nfact;' .
			'for (i in 1:nrfactors){ result.out <- fa(data, nfactors = i); fa.diagram(result.out)}';

		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);		

		$needles = array('nrfactors', 'graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		$plots = $results['graphics'];
		
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		
		//show scree
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUFactorAnalysis_graph_eigenvalues'));
		$template->setVariable('PLOT', "<img src='data:image/png;base64," . $plots[0] . "'>");
		$template->setVariable('DESCRIPTION', sprintf(
				$this->plugin->txt('tst_OpenCPUFactorAnalysis_graph_eigenvalues_description'),
				$results['nrfactors']));
		$template->parseCurrentBlock("accordion_plot");
		
		//show Graph factor loading matrices
		$plots_length = count((array)$plots);
		for ($i = 1; $i < $plots_length; $i++) {
			$template->setCurrentBlock("accordion_plot");
			$template->setVariable('TITLE', sprintf(
					$this->plugin->txt('tst_OpenCPUFactorAnalysis_graph_factorloading'),
					$i));
			$template->setVariable('PLOT', "<img src='data:image/png;base64," . $plots[$i] . "'>");
			$template->parseCurrentBlock("accordion_plot");
		}

		$details->customHTML = $template->get();
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		return $details;
	}
}