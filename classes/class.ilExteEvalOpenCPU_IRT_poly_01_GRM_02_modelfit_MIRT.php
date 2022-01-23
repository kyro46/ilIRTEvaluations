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
	protected $allowed_test_types = array(self::TEST_TYPE_FIXED);
	
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
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU_Basedata");
		$server = $config['server'];
		$dichotomization = $config['dichotomization'];
		
		$dataDichotomized = ilExteEvalOpenCPU::getBasicData($this->getData(),$dichotomization);
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
		//"m4pl <- mirt(dataDichotomized, 1, itemtype='4PL');" .
		"rsm <- tryCatch({mirt(data, 1, itemtype='rsm')},error = function(e){mirt(dataDichotomized, 1, itemtype='rsm')});" .
		"grm <- mirt(data, 1, itemtype='graded');" .
		"gpcm <- mirt(data, 1, itemtype='gpcm');" .
		//"nominal <- mirt(data, 1, itemtype='nominal');" .
		"AIC <- c(Rasch=rasch@Fit\$AIC,'2PL'=m2pl@Fit\$AIC,'3PL'=m3pl@Fit\$AIC,RSM=rsm@Fit\$AIC,GRM=grm@Fit\$AIC,GPCM=gpcm@Fit\$AIC);" .
		"BIC <- c(Rasch=rasch@Fit\$BIC,'2PL'=m2pl@Fit\$BIC,'3PL'=m3pl@Fit\$BIC,RSM=rsm@Fit\$BIC,GRM=grm@Fit\$BIC,GPCM=gpcm@Fit\$BIC);" .
		"SABIC <- c(Rasch=rasch@Fit\$SABIC,'2PL'=m2pl@Fit\$SABIC,'3PL'=m3pl@Fit\$SABIC,RSM=rsm@Fit\$SABIC,GRM=grm@Fit\$SABIC,GPCM=gpcm@Fit\$SABIC);" .
		"HQ <- c(Rasch=rasch@Fit\$HQ,'2PL'=m2pl@Fit\$HQ,'3PL'=m3pl@Fit\$HQ,RSM=rsm@Fit\$HQ,GRM=grm@Fit\$HQ,GPCM=gpcm@Fit\$HQ);" .
		"logLik <- c(Rasch=rasch@Fit\$logLik,'2PL'=m2pl@Fit\$logLik,'3PL'=m3pl@Fit\$logLik,RSM=rsm@Fit\$logLik,GRM=grm@Fit\$logLik,GPCM=gpcm@Fit\$logLik);" .
		"converge <- c(Rasch=rasch@OptimInfo\$converged,'2PL'=m2pl@OptimInfo\$converged,'3PL'=m3pl@OptimInfo\$converged,RSM=rsm@OptimInfo\$converged,GRM=grm@OptimInfo\$converged,GPCM=gpcm@OptimInfo\$converged);" .
		//"plotData <- data.frame(AIC,BIC,SABIC,HQ);" .
		"table <- data.frame(AIC,BIC,SABIC,HQ,logLik,converge);" .
		"fit_Rasch <- c(AIC=rasch@Fit\$AIC,BIC=rasch@Fit\$BIC,SABIC=rasch@Fit\$SABIC,HQ=rasch@Fit\$HQ);" .
		"fit_2PL <- c(AIC=m2pl@Fit\$AIC,BIC=m2pl@Fit\$BIC,SABIC=m2pl@Fit\$SABIC,HQ=m2pl@Fit\$HQ);" .
		"fit_3PL <- c(AIC=m3pl@Fit\$AIC,BIC=m3pl@Fit\$BIC,SABIC=m3pl@Fit\$SABIC,HQ=m3pl@Fit\$HQ);" .
		//"#fit_4PL <- c(AIC=m4pl@Fit\$AIC,BIC=m4pl@Fit\$BIC,SABIC=m4pl@Fit\$SABIC,HQ=m4pl@Fit\$HQ);" .
		"fit_RSM <- c(AIC=rsm@Fit\$AIC,BIC=rsm@Fit\$BIC,SABIC=rsm@Fit\$SABIC,HQ=rsm@Fit\$HQ);" .
		"fit_GRM <- c(AIC=grm@Fit\$AIC,BIC=grm@Fit\$BIC,SABIC=grm@Fit\$SABIC,HQ=grm@Fit\$HQ);" .
		"fit_GPCM <- c(AIC=gpcm@Fit\$AIC,BIC=gpcm@Fit\$BIC,SABIC=gpcm@Fit\$SABIC,HQ=gpcm@Fit\$HQ);" .
		//"#fit_nominal <- c(AIC=nominal@Fit\$AIC,BIC=nominal@Fit\$BIC,SABIC=nominal@Fit\$SABIC,HQ=nominal@Fit\$HQ);" .
		"plotData <- data.frame(fit_Rasch,fit_2PL,fit_3PL,fit_RSM,fit_GRM,fit_GPCM);" .
		"colnames(plotData)<- c('Rasch-Modell','2PL-Modell','3PL-Modell', 'RSM', 'GRM', 'GPCM');" .
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
				ilExteStatColumn::_create('bic', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_BIC'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('sabic', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_SABIC'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('hq', $this->plugin->txt('tst_OpenCPUPolytomousGRM_modelfit_table_HQ'),ilExteStatColumn::SORT_NUMBER),
		);
		
		//rows
		foreach ($serialized as $row)
		{	
			$details->rows[] = array(
					'model' => ilExteStatValue::_create($row['_row'], ilExteStatValue::TYPE_TEXT, 0),
					'convergence' => ilExteStatValue::_create($row['converge'], ilExteStatValue::TYPE_BOOLEAN, 0),
					'aic' => ilExteStatValue::_create($row['AIC'], ilExteStatValue::TYPE_NUMBER, 3),
					'bic' => ilExteStatValue::_create($row['BIC'], ilExteStatValue::TYPE_NUMBER, 3),
					'sabic' => ilExteStatValue::_create($row['SABIC'], ilExteStatValue::TYPE_NUMBER, 3, NULL),
					'hq' => ilExteStatValue::_create($row['HQ'], ilExteStatValue::TYPE_NUMBER, 3),
			);
		}
		
		return $details;
	}
}