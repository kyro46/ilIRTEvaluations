<?php

/**
 * Calculates fit statistics to compare various models via OpenCPU and MIRT
 */
class ilExteEvalOpenCPU_IRT_poly_01_GRM_02_modelfit_MIRT extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUPolytomousGRM_modelfit';
	
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
	 * Calculate model-fit
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
		
		$dataDichotomized = ilExteEvalOpenCPU::getBasicData($this->getData(),TRUE); //TRUE -> dichotomize at 50% of reachable points
		$data = ilExteEvalOpenCPU::getBasicData($this->getData()); //TRUE -> dichotomize at 50% of reachable points
		
		$columnsLegend = intdiv(count($this->data->getAllQuestions()),10);
		$path = "/ocpu/library/base/R/identity";
		
		$query["x"] =
		"library(mirt);" .
		"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
		"dataDichotomized <- read.csv(text='{$dataDichotomized['csv']}', row.names = 1, header= TRUE);" .
		"rasch <- mirt(dataDichotomized, 1, itemtype='Rasch');" .
		"m2pl <- mirt(dataDichotomized, 1, itemtype='2PL');" .
		"m3pl <- mirt(dataDichotomized, 1, itemtype='3PL');" .
		"m4pl <- mirt(dataDichotomized, 1, itemtype='4PL');" .
		"rsm <- tryCatch({mirt(data, 1, itemtype='rsm')},error = function(e){mirt(dataDichotomized, 1, itemtype='rsm')});" .
		"grm <- mirt(data, 1, itemtype='graded');" .
		"gpcm <- mirt(data, 1, itemtype='gpcm');" .
		"nominal <- mirt(data, 1, itemtype='nominal');" .
		"AIC <- c(Rasch=rasch@Fit\$AIC,'2PL'=m2pl@Fit\$AIC,'3PL'=m3pl@Fit\$AIC,'4PL'=m4pl@Fit\$AIC,RSM=rsm@Fit\$AIC,GRM=grm@Fit\$AIC,GPCM=gpcm@Fit\$AIC,nominal=nominal@Fit\$AIC);" .
		"AICc <- c(Rasch=rasch@Fit\$AICc,'2PL'=m2pl@Fit\$AICc,'3PL'=m3pl@Fit\$AICc,'4PL'=m4pl@Fit\$AICc,RSM=rsm@Fit\$AICc,GRM=grm@Fit\$AICc,GPCM=gpcm@Fit\$AICc,nominal=nominal@Fit\$AICc);" .
		"SABIC <- c(Rasch=rasch@Fit\$SABIC,'2PL'=m2pl@Fit\$SABIC,'3PL'=m3pl@Fit\$SABIC,'4PL'=m4pl@Fit\$SABIC,RSM=rsm@Fit\$SABIC,GRM=grm@Fit\$SABIC,GPCM=gpcm@Fit\$SABIC,nominal=nominal@Fit\$SABIC);" .
		"BIC <- c(Rasch=rasch@Fit\$BIC,'2PL'=m2pl@Fit\$BIC,'3PL'=m3pl@Fit\$BIC,'4PL'=m4pl@Fit\$BIC,RSM=rsm@Fit\$BIC,GRM=grm@Fit\$BIC,GPCM=gpcm@Fit\$BIC,nominal=nominal@Fit\$BIC);" .
		"HQ <- c(Rasch=rasch@Fit\$HQ,'2PL'=m2pl@Fit\$HQ,'3PL'=m3pl@Fit\$HQ,'4PL'=m4pl@Fit\$HQ,RSM=rsm@Fit\$HQ,GRM=grm@Fit\$HQ,GPCM=gpcm@Fit\$HQ,nominal=nominal@Fit\$HQ);" .
		"logLik <- c(Rasch=rasch@Fit\$logLik,'2PL'=m2pl@Fit\$logLik,'3PL'=m3pl@Fit\$logLik,'4PL'=m4pl@Fit\$logLik,RSM=rsm@Fit\$logLik,GRM=grm@Fit\$logLik,GPCM=gpcm@Fit\$logLik,nominal=nominal@Fit\$logLik);" .
		"converge <- c(Rasch=rasch@OptimInfo\$converged,'2PL'=m2pl@OptimInfo\$converged,'3PL'=m3pl@OptimInfo\$converged,'4PL'=m4pl@OptimInfo\$converged,RSM=rsm@OptimInfo\$converged,GRM=grm@OptimInfo\$converged,GPCM=gpcm@OptimInfo\$converged,nominal=nominal@OptimInfo\$converged);" .
		"plotData <- data.frame(AIC,AICc,SABIC,BIC,HQ);" .
		"table <- data.frame(AIC,AICc,SABIC,BIC,HQ,logLik,converge);" .
		"barplot(t(as.matrix(plotData)), beside=TRUE, legend.text = TRUE, args.legend = list(x = 'center'));";
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		//prepare results
		$needles = array('table','graphics' );
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		
		$serialized = json_decode(stripslashes($results['table']),TRUE);
		$plots = $results['graphics'];
		
		// create accordions for plots and textual summaries
		// Expected Score
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("accordion_plot");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_acc_indicators'));
		$plot = '';
		$plot = "<img src='data:image/png;base64," . $plots[0] . "'>";
		$template->setVariable('PLOT', $plot);
		$template->parseCurrentBlock("accordion_plot");

		//prepare and create output of plots
		$customHTML = $template->get();
		$details->customHTML = $customHTML;

		//header
		$details->columns = array (
				ilExteStatColumn::_create('model', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_model'),ilExteStatColumn::SORT_NONE),
				ilExteStatColumn::_create('convergence', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_convergence'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('aic', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_AIC'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('aicc', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_AICc'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('sabic', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_SABIC'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('bic', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_BIC'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('hq', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_HQ'),ilExteStatColumn::SORT_NUMBER),
		);
		
		//rows
		$i = 0;
		foreach ($serialized as $row)
		{	
			$details->rows[] = array(
					'model' => ilExteStatValue::_create($row['_row'], ilExteStatValue::TYPE_TEXT, 0),
					'convergence' => ilExteStatValue::_create($row['converge'], ilExteStatValue::TYPE_BOOLEAN, 0),
					'aic' => ilExteStatValue::_create($row['AIC'], ilExteStatValue::TYPE_NUMBER, 3),
					'aicc' => ilExteStatValue::_create($row['AICc'], ilExteStatValue::TYPE_NUMBER, 3),
					'sabic' => ilExteStatValue::_create($row['SABIC'], ilExteStatValue::TYPE_NUMBER, 3, NULL),
					'bic' => ilExteStatValue::_create($row['BIC'], ilExteStatValue::TYPE_NUMBER, 3),
					'hq' => ilExteStatValue::_create($row['HQ'], ilExteStatValue::TYPE_NUMBER, 3),
			);
		$i++;
		}
		
		return $details;
	}
}