<?php

/**
 * Provides a factor analysis via OpenCPU
 */
class ilExteEvalOpenCPUFactorAnalysis extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUFactorAnalysis';

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
		
		$path = "/ocpu/library/base/R/identity";
		$query["x"] = 'library(psych);' .
			'library(GPArotation);' .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			// 'cortestbartlett <- cortest.bartlett(data);' .
			// 'kmo <- KMO(data);' .
			'factors <- fa.parallel(data, fa = "fa");' .
			'nrfactors <- factors$nfact;' .
			'result.out <- fa(data, nfactors = nrfactors, fm="pa", max.iter = 100, rotate = "oblimin");' .
			// 'e.values <- result.out$e.values;' .
			'fa.diagram(result.out);';

		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);		

		$needles = array('e.values','graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		//$serialized = json_decode(stripslashes($results['e.values']),TRUE);
		$plots = $results['graphics'];
		
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		
		//show scree
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUFactorAnalysis_graph_eigenvalues'));
		$template->setVariable('PLOT', "<img src='data:image/png;base64," . $plots[0] . "'>");
		$template->parseCurrentBlock("accordion_plot");
		
		//show Graph factor loading matrices
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUFactorAnalysis_graph_factorloading'));
		$template->setVariable('PLOT', "<img src='data:image/png;base64," . $plots[1] . "'>");
		$template->parseCurrentBlock("accordion_plot");
		
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("plot");

		$details->customHTML = $template->get();
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//TODO ?
		
		return $details;
	}
}