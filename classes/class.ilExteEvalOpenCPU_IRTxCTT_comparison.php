<?php

/**
 * Calculates comparisons between CTT and IRT (the GRM) via OpenCPU and MIRT
 * TODO Handle questions removed due to zero variance
 * TODO Restructure to insert a NA-row of type text instead of 0
 * TODO Gives an evaluation of the model-fit
 */
class ilExteEvalOpenCPU_IRTxCTT_comparison extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPU_IRTxCTT_comparison';
	
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
		$path = "/ocpu/library/base/R/identity";
		
		//TODO - difficulty calculation for ctt has to be adjusted for polytomous tests. Not usable yet!
		$query["x"] =
			"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
			"library(CTT);" .
			"ctt_itemanalysis <- itemAnalysis(data);" .
			"library(mirt);" .
			"grm <- mirt(data,1,itemtype='graded');" .
			"coef <- coef(grm);" .
			"irtdifdisc <- data.frame(matrix(unlist(coef), nrow=length(coef), byrow=TRUE));" .
			"irtdifdisc <- irtdifdisc[-nrow(irtdifdisc),];" .
			//"# ctt difficulty and irt d" .
			"cttdifIRT <- data.frame(ctt_itemanalysis\$itemReport\$itemMean, irtdifdisc\$X2);" .
			"plot(cttdifIRT);" .
			//"# ltm calculations maybe later -> ExBisCorr " .
			"library(ltm);" .
			"ltm_descript <- descript(data);" .
			//"# ltm bisCorr with item itr-diff" .
			//"ltmBisCorrIRT <- data.frame(ltm_descript\$bisCorr,irtdifdisc\$X1);" .
			//"plot_ltmBisCorrIRT <- plot(ltmBisCorrIRT);" .
			//"# ltm bisCorr without item itr-diff" .
			"ltmExBisCorrIRT <- data.frame(ltm_descript\$ExBisCorr,irtdifdisc\$X1);" .
			"plot(ltmExBisCorrIRT);" .
			//"# ctt bis and irt a" .
			//"cttbisIRT <- data.frame(ctt_itemanalysis\$itemReport\$bis, irtdifdisc\$X1);" .
			//"plot_cttbisIRT <- plot(cttbisIRT);" .
			//"# ctt pbis and irt a" .
			//"cttpbisIRT <- data.frame(ctt_itemanalysis\$itemReport\$pBis, irtdifdisc\$X1);" .
			//"plot_cttpbisIRT <- plot(cttpbisIRT);" .
			//"# ctt sumscore and irt person ability" .
			"sumscore <- rowSums(data);" .
			"person_ability <- fscores(grm, method='MAP', full.scores=TRUE);" .
			"plot(sumscore,person_ability);";
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('cttdifIRT', 'ltmExBisCorrIRT', 'coef', 'graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);

		$cttdifIRT = json_decode(stripslashes($results['cttdifIRT']),TRUE);
		$ltmExBisCorrIRT = json_decode(stripslashes($results['ltmExBisCorrIRT']),TRUE);
		$coef = json_decode(stripslashes($results['coef']),TRUE);
				
		// create accordions for plots and textual summaries
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_acc_difficulty'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," .  $results['graphics'][0] . "'>"; // difficulty
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");

		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_acc_discrimination'));
		$plot = '';
		$plot .= "<img src='data:image/png;base64," .  $results['graphics'][1] . "'>";	// pointbiserial correlation without item
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_acc_ability'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," .  $results['graphics'][2] . "'>"; // sum score and ability
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");
		
		//prepare and create output of plots
		$customHTML = $template->get();

		$details->customHTML = $customHTML;
		
		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('CTT_Dif', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_cttdif'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('IRT_Dif', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_irtdif'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('CTT_PBIS', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_cttpbis'), ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('IRT_Dis', $this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_irtdis'), ilExteStatColumn::SORT_NUMBER),
		);

		//rows
		$i = 0;
		foreach ($this->data->getAllQuestions() as $question)
		{
			if (array_key_exists('X' . $question->question_id, $coef)) {
				$details->rows[] = array(
						'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
						'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
						'CTT_Dif' => ilExteStatValue::_create($cttdifIRT[$i]['ctt_itemanalysis.itemReport.itemMean'], ilExteStatValue::TYPE_NUMBER, 3),
						'IRT_Dif' => ilExteStatValue::_create($cttdifIRT[$i]['irtdifdisc.X2'], ilExteStatValue::TYPE_NUMBER, 3),
						'CTT_PBIS' => ilExteStatValue::_create($ltmExBisCorrIRT[$i]['ltm_descript.ExBisCorr'], ilExteStatValue::TYPE_NUMBER, 3),
						'IRT_Dis' => ilExteStatValue::_create($ltmExBisCorrIRT[$i]['irtdifdisc.X1'], ilExteStatValue::TYPE_NUMBER, 3),
				);
			} else { // if the question was removed due to no variance, insert empty row
				$details->rows[] = array(
						'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
						'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
						'CTT_Dif' => ilExteStatValue::_create(1, ilExteStatValue::TYPE_NUMBER, 0),
				);
			}
			$i++;
		}
		
		return $details;
	}
}