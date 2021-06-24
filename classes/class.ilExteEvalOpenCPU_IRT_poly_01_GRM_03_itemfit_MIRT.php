<?php

/**
 * Calculates fit statistics for the items for the GRM via OpenCPU and MIRT
 */
class ilExteEvalOpenCPU_IRT_poly_01_GRM_03_itemfit_MIRT extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGRM_itemfit';
	
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
		"library(mirt);" .
		"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
		"model <- mirt(data, 1, itemtype='graded');" .
		"for (i in model@Data\$rowID) {print(itemfit(model, empirical.plot = i))};" .
		//"itemfit <- itemfit(model, c('S_X2','X2','G2','infit'));";
		//"itemfit <- itemfit(model, c('S_X2','infit'));";
		"itemfit <- itemfit(model, c('S_X2'));";
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('itemfit', 'graphics' );
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		
		$serialized = json_decode(stripslashes($results['itemfit']),TRUE);
		$plots = $results['graphics'];
		
		// create accordions for plots and textual summaries
		// Expected Score
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_acc_empiricalPlot'));
		$plot = '';
		for ($i = 0; $i < sizeof($plots); $i++) {
			$plot .= "<img src='data:image/png;base64," . $plots[$i] . "'>";
		}
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		//prepare and create output of plots
		$customHTML = $template->get();
		
		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_nr', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_itemnr'),ilExteStatColumn::SORT_NONE),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('outfit', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_outfit'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('z.outfit', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_z.outfit'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('infit', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_infit'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('z.infit', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_z.infit'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_X2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('df.X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_df.X2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('RMSEA.X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_RMSEA.X2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('p.X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_p.X2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('G2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_G2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('df.G2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_df.G2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('RMSEA.G2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_RMSEA.G2'),ilExteStatColumn::SORT_NUMBER),
				//ilExteStatColumn::_create('p.G2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_p.G2'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('S_X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_S_X2'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('df.S_X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_df.S_X2'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('RMSEA.S_X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_RMSEA.S_X2'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('p.S_X2', $this->plugin->txt('tst_OpenCPUPolytomousGRM_itemfit_table_p.S_X2'),ilExteStatColumn::SORT_NUMBER),
		);
		
		//rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			if ($question->question_id == substr($serialized[$i]['item'], 1)) {
				$details->rows[] = array(
						'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
						'question_nr' => ilExteStatValue::_create('#'.($i+1), ilExteStatValue::TYPE_TEXT, 0),
						'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
						//'outfit' => ilExteStatValue::_create($serialized[$i]['outfit'], ilExteStatValue::TYPE_NUMBER, 3),
						//'z.outfit' => ilExteStatValue::_create($serialized[$i]['z.outfit'], ilExteStatValue::TYPE_NUMBER, 3, NULL),
						//'infit' => ilExteStatValue::_create($serialized[$i]['infit'], ilExteStatValue::TYPE_NUMBER, 3),
						//'z.infit' => ilExteStatValue::_create($serialized[$i]['z.infit'], ilExteStatValue::TYPE_NUMBER, 3),
						//'X2' => ilExteStatValue::_create($serialized[$i]['X2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'df.X2' => ilExteStatValue::_create($serialized[$i]['df.X2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'RMSEA.X2' => ilExteStatValue::_create($serialized[$i]['RMSEA.X2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'p.X2' => ilExteStatValue::_create($serialized[$i]['p.X2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'G2' => ilExteStatValue::_create($serialized[$i]['G2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'df.G2' => ilExteStatValue::_create($serialized[$i]['df.G2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'RMSEA.G2' => ilExteStatValue::_create($serialized[$i]['RMSEA.G2'], ilExteStatValue::TYPE_NUMBER, 3),
						//'p.G2' => ilExteStatValue::_create($serialized[$i]['p.G2'], ilExteStatValue::TYPE_NUMBER, 3),
						'S_X2' => ilExteStatValue::_create($serialized[$i]['S_X2'], ilExteStatValue::TYPE_NUMBER, 3),
						'df.S_X2' => ilExteStatValue::_create($serialized[$i]['df.S_X2'], ilExteStatValue::TYPE_NUMBER, 3),
						'RMSEA.S_X2' => ilExteStatValue::_create($serialized[$i]['RMSEA.S_X2'], ilExteStatValue::TYPE_NUMBER, 3),
						'p.S_X2' => ilExteStatValue::_create($serialized[$i]['p.S_X2'], ilExteStatValue::TYPE_NUMBER, 3),
				);
			} else { // if the question was removed due to no variance, insert empty row
				$details->rows[] = array(
						'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
						'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
				);
			}
			$i++;
		}
		
		return $details;
	}
}