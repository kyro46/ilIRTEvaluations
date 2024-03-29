<?php

/**
 * Calculates comparisons between CTT and IRT (the GRM) via OpenCPU and MIRT
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
	protected $lang_prefix = 'tst_OpenCPU_IRTxCTT_comparison';
	
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
	 * Calculate the comparisons between CTT and IRT
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
		$showCode = $config['show_R_code'];
		
		$data = ilExteEvalOpenCPU::getBasicData($this->getData());
		
		// get an array of all questions with their max points
		// needed to calculate the CTT-difficulty in R
		$maxPoints = array();
		
		foreach ($this->data->getAllQuestions() as $question)
		{
			if (!in_array($question->question_id, $data['removed']))
			$maxPoints[$question->question_id] = $question->maximum_points;
		}

		$maxPointsJSON = json_encode($maxPoints);
		
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
    		"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" . "\n" .
    		"library(jsonlite);" . "\n" .
    		"maxPoints <- fromJSON('{$maxPointsJSON}');" . "\n" .
    			
    		"difficultyManual <- rbind(colSums(data,na.rm=TRUE),t(maxPoints));" . "\n" .
    		"difficultyManual <- t(difficultyManual);" . "\n" .
    		"difficultyManual <- data.frame(difficultyManual);" . "\n" .
    			
    		"difficultyManual\$X1 <- as.numeric(difficultyManual\$X1);" . "\n" .
    		"difficultyManual\$X2 <- as.numeric(difficultyManual\$X2);" . "\n" .
    			
    		"difficultyManual\$maxObtainable <- difficultyManual\$X2 * nrow(data);" . "\n" .
    		"difficultyManual\$difficulty <- difficultyManual\$X1 / difficultyManual\$maxObtainable ;" . "\n" .
    
    		"library(CTT);" . "\n" .
    		"ctt_itemanalysis <- itemAnalysis(data);" . "\n" .
    		"library(mirt);" . "\n" .
    		"grm <- mirt(data,1,itemtype='graded');" . "\n" .
    		"coef <- coef(grm, simplify=TRUE);" . "\n" .
    		"questionlist <- coef(grm);" . "\n" .
    		"discrimination <- coef\$items[,1];" .  "\n" .//select discrimination in first row
    		"meandifficulty <- tryCatch({rowMeans(coef\$items[,-1], na.rm=TRUE)},error = function(e){coef\$items[,-1]});" .  "\n" .// rowsum only for polytomous items
    		"irtdifdisc <- data.frame(t(rbind(discrimination,meandifficulty)));" . "\n" .
    			
			//ctt difficulty and irt d
    		"cttdifIRT <- data.frame(difficultyManual\$difficulty, irtdifdisc\$meandifficulty);" . "\n" .
    		"plot(cttdifIRT, xlab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_cttdif')}', ylab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_irtdif')}');" . "\n" .
			
			//ltm calculations -> ExBisCorr
			// ExBisCorr only possible for dichotomous items, so we use the dichotomized data
		    //"library(ltm);" . "\n" .
		    //"ltm_descript <- descript(data);" .	 "\n" .
		    //"ltmExBisCorrIRT <- data.frame(ltm_descript\$ExBisCorr,irtdifdisc\$discrimination);" . "\n" .
		    //"plot(ltmExBisCorrIRT);" . "\n" .
			
		    //ctt pbis and irt a
		    "cttpbisIRT <- data.frame(ctt_itemanalysis\$itemReport\$pBis, irtdifdisc\$discrimination);" . "\n" .
		    "plot(cttpbisIRT, xlab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_cttpbis')}', ylab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_table_irtdis')}');" . "\n" .
			
			//ctt sumscore and irt person ability
		    "sumscore <- rowSums(data);" . "\n" .
		    "person_ability <- fscores(grm, method='MAP', full.scores=TRUE);" . "\n" .
			"plot(sumscore,person_ability, xlab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_plot_cttsumscore')}', ylab='{$this->plugin->txt('tst_OpenCPU_IRTxCTT_comparison_plot_irt_ability')}');";

		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('cttdifIRT', 'cttpbisIRT', 'coef', 'questionlist', 'graphics');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);

		$cttdifIRT = json_decode(stripslashes($results['cttdifIRT']),TRUE);
		$cttpbisIRT = json_decode(stripslashes($results['cttpbisIRT']),TRUE);
		$coef = json_decode(stripslashes($results['coef']),TRUE);
		$questionlist = json_decode(stripslashes($results['questionlist']),TRUE);
		
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
		
		// provide R-Code
		if ($showCode) {
		    $template->setCurrentBlock("accordion_plot");
		    $template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPU_R_code'));
		    $template->setVariable('DESCRIPTION', nl2br($query['x']));
		    $template->parseCurrentBlock("accordion_plot");
		}
		
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
			if (array_key_exists('X' . $question->question_id, $questionlist)) {
				$details->rows[] = array(
						'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
						'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
						'CTT_Dif' => ilExteStatValue::_create($cttdifIRT[$i]['difficultyManual.difficulty'], ilExteStatValue::TYPE_NUMBER, 2),
						'IRT_Dif' => ilExteStatValue::_create($cttdifIRT[$i]['irtdifdisc.meandifficulty'], ilExteStatValue::TYPE_NUMBER, 2),
						'CTT_PBIS' => ilExteStatValue::_create($cttpbisIRT[$i]['ctt_itemanalysis.itemReport.pBis'], ilExteStatValue::TYPE_NUMBER, 2),
						'IRT_Dis' => ilExteStatValue::_create($cttpbisIRT[$i]['irtdifdisc.discrimination'], ilExteStatValue::TYPE_NUMBER, 2),
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