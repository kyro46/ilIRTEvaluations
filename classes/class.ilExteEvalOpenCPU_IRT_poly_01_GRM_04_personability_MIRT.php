<?php

/**
 * Calculates ability and fit statistics for all partipicants for the GRM via OpenCPU and MIRT
 */
class ilExteEvalOpenCPU_IRT_poly_01_GRM_04_personability_MIRT extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGRM_personability';
	
	/**
	 * Calculate the person-fit and the ability
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
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
			"library(mirt);" .
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"model <- mirt(data, 1, itemtype='graded');" .
			"ability_map <- fscores(model, method='MAP', full.scores=TRUE, full.scores.SE=TRUE);" .  // map works also works for 100% correct or false answer patterns
			"personfitZh <- personfit(model);" .
			"plot(ability_map, xlab='Estimated Ability', ylab='Standard Error');";

		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('ability_map', 'personfitZh', 'graphics' );
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		
		$ability_map = json_decode(stripslashes($results['ability_map']),TRUE);
		$personfitZh = json_decode(stripslashes($results['personfitZh']),TRUE);

		// create accordions for plots and textual summaries
		// Expected Score
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_personability_acc_ability'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," .  $results['graphics'][0] . "'>";
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		//prepare and create output of plots
		$customHTML = $template->get();
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('person_id', $this->plugin->txt('tst_OpenCPUPolytomousGRM_personability_table_personid'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_ability', $this->plugin->txt('tst_OpenCPUPolytomousGRM_personability_table_ability'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_standarderror', $this->plugin->txt('tst_OpenCPUPolytomousGRM_personability_table_standarderror'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('grm_personfitZh', $this->plugin->txt('tst_OpenCPUPolytomousGRM_personability_table_personfitZh'), ilExteStatColumn::SORT_NUMBER),
		);

		//rows
		// participant ids needed but not existent in ability_map, extract from source 
		$basicData = str_getcsv($data['csv'], "\n"); //parse the rows
		foreach($basicData as &$row) $row = str_getcsv($row, ";"); //parse the items in rows 
		array_shift($basicData); // header is not needed because there is no participant id
		
		$i = 0;
		foreach ($basicData as $participant)
		{
			$details->rows[] = array(
					'person_id' => ilExteStatValue::_create($participant[0], ilExteStatValue::TYPE_NUMBER, 0),
					'grm_ability' => ilExteStatValue::_create($ability_map[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'grm_standarderror' => ilExteStatValue::_create($ability_map[$i][1], ilExteStatValue::TYPE_NUMBER, 3, NULL),
					'grm_personfitZh' => ilExteStatValue::_create($personfitZh[$i]['Zh'], ilExteStatValue::TYPE_NUMBER, 3, NULL),
			);
			$i++;
		}
		
		return $details;
	}
}