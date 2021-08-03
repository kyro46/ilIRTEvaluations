<?php

/**
 * Calculates the internal consistency and Spearman Brown Formula via OpenCPU
 */
class ilExteEvalOpenCPU_CTT_01_Alpha extends ilExteEvalTest
{
	/**
	 * @var bool	evaluation provides a single value for the overview level
	 */
	protected $provides_value = true;
	
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
	protected $lang_prefix = 'tst_OpenCPUAlpha';

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
	 * Get a list of available parameters
	 *	@return ilExteStatParam[]
	 */
	public function getAvailableParams()
	{
		return array(
				ilExteStatParam::_create('min_qst', ilExteStatParam::TYPE_INT, 2),
				ilExteStatParam::_create('min_part', ilExteStatParam::TYPE_INT, 2),
				ilExteStatParam::_create('min_difference', ilExteStatParam::TYPE_FLOAT, 0.05),
				ilExteStatParam::_create('min_medium', ilExteStatParam::TYPE_FLOAT, 0.7),
				ilExteStatParam::_create('min_good', ilExteStatParam::TYPE_FLOAT, 0.8)
		);
	}
	
	/**
	 * Calculate and get the single value for a test
	 *
	 * @return ilExteStatValue
	 */
	public function calculateValue()
	{
		require_once('utility/class.ilExteEvalOpenCPU.php');
				
		$value = new ilExteStatValue;
		$value->type = ilExteStatValue::TYPE_NUMBER;
		$value->precision = 2;
		$value->value = null;

		$number_of_questions = count($this->data->getAllQuestions());
		$number_of_users = count($this->data->getAllParticipants());
		
		// check minimum number of questions
		if ($number_of_questions < $this->getParam('min_qst'))
		{
			$value->alert = ilExteStatValue::ALERT_UNKNOWN;
			$value->comment = sprintf($this->txt('min_qst_alert'), $this->getParam('min_qst'));
			
			$this->provides_details = false;
			return $value;
		}
		
		// check minimum number of users
		if ($number_of_users < $this->getParam('min_part') && $this->getParam('min_part') > 2)
		{
			$value->alert = ilExteStatValue::ALERT_UNKNOWN;
			$value->comment = sprintf($this->txt('min_part_alert'), $this->getParam('min_part'));
			
			$this->provides_details = false;
			
			return $value;
		}
		elseif ($number_of_users < 2)
		{
			$value->alert = ilExteStatValue::ALERT_UNKNOWN;
			$value->comment = sprintf($this->txt('min_part_alert'), 2);
			
			$this->provides_details = false;
			
			return $value;
		}
		
		$plugin = new ilExtendedTestStatisticsPlugin;
		$config = $plugin->getConfig()->getEvaluationParameters("ilExteEvalOpenCPU_Basedata");
		$server = $config['server'];

		$data = ilExteEvalOpenCPU::getBasicData($this->getData(),FALSE, FALSE, FALSE);
		
		$path = "/ocpu/library/base/R/identity/json";
		$query["x"] = 	"library(ltm);" .
						"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" .
						"result <- cronbach.alpha(data, na.rm = TRUE);" .
						"library(jsonlite);" . 
						"toJSON(result[1])";
		

		$result = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);//Format: {\\"alpha\\":[x.xxx]}\n
		$serialized = json_decode(substr(stripslashes($result), 2, -3),TRUE);

		$value->value = $serialized['alpha'][0];
		
		// Alert good quality
		if ( $this->getParam('min_good') > 0)
		{
			if ($value->value >= $this->getParam('min_good'))
			{
				$value->alert = ilExteStatValue::ALERT_GOOD;
				return $value;
			}
			else
			{
				$value->alert = ilExteStatValue::ALERT_BAD;
			}
		}
		
		// Alert medium quality
		if ( $this->getParam('min_medium') > 0)
		{
			if ($value->value >= $this->getParam('min_medium'))
			{
				$value->alert = ilExteStatValue::ALERT_MEDIUM;
				return $value;
			}
			else
			{
				$value->alert = ilExteStatValue::ALERT_BAD;
			}
		}

		return $value;
	}
	
	/**
	 * Calculate and classify alpha per removed item
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
		
		$data = ilExteEvalOpenCPU::getBasicData($this->getData(),FALSE, FALSE, FALSE);
		
		$path = "/ocpu/library/base/R/identity";
		$query["x"] = 	"library(ltm);" . 
						"data <- read.csv(text='{$data['csv']}', row.names = 1, header= TRUE);" . 
						"result <- descript(data); " .
						"library(jsonlite);" .
						"cronlist <- toJSON(result\$alpha);" .
						"cron <- cronbach.alpha(data, na.rm = TRUE);" .
						"library(CTT);" .
						"spearmanbrownformula <- spearman.brown(cron\$alpha, {$this->getParam('min_good')}, 'r')\$n.new;";
		
		$session = ilExteEvalOpenCPU::callOpenCPU($server, $path, $query);
		
		if ($session == FALSE) {
			$details->customHTML = $this->plugin->txt('tst_OpenCPU_unreachable');
			return $details;
		}
		
		$needles = array('cronlist', 'spearmanbrownformula');
		$results = ilExteEvalOpenCPU::retrieveData($server, $session, $needles);
		
		$serialized = json_decode(substr(stripslashes($results['cronlist']), 2, -3),TRUE);
		$spearmanbrownformula = $results['spearmanbrownformula'];
		
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Text.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setCurrentBlock("paragraph");
		$template->setVariable('TITLE', $this->plugin->txt('tst_OpenCPUAlpha_SpearmanBrownFormula'));
		$template->setVariable('TEXT',  sprintf(
											$this->plugin->txt('tst_OpenCPUAlpha_SpearmanBrownFormulaText'),
											(string) $spearmanbrownformula,
											(string) $this->getParam('min_good')
											)
								);
		$template->parseCurrentBlock("paragraph");
				
		//prepare and create output of custom HTML
		$customHTML = $template->get();
		$details->customHTML = $customHTML;

		//header
		$details->columns = array (
				ilExteStatColumn::_create('question_id', $this->plugin->txt('tst_OpenCPU_table_id'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('question_title', $this->plugin->txt('tst_OpenCPU_table_title'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('alpha_if_removed', $this->plugin->txt('tst_OpenCPUAlpha_table_alphaIfRemoved'),ilExteStatColumn::SORT_NUMBER),
				ilExteStatColumn::_create('alpha_if_removed_difference', $this->plugin->txt('tst_OpenCPUAlpha_table_alphaIfRemoved_difference'),
				ilExteStatColumn::SORT_NUMBER,  $this->plugin->txt('tst_OpenCPUAlpha_table_alphaIfRemoved_difference_comment'))	
		);
		
		//pupulate rows
		$i = 1; //because $serialized[alpha][0] contains the overall alpha
		foreach ($this->data->getAllQuestions() as $question)
		{
			$indicator = NULL;
			$difference = $serialized[$i][0] - $serialized[0][0];
			$difference > 0 ? $indicator = ilExteStatValue::ALERT_BAD : $indicator = ilExteStatValue::ALERT_GOOD;
			abs($difference) > $this->getParam('min_difference') ?: $indicator = ilExteStatValue::ALERT_MEDIUM;

			$details->rows[] = array(
					'question_id' => ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0),
					'question_title' => ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT, 0),
					'alpha_if_removed' => ilExteStatValue::_create($serialized[$i][0], ilExteStatValue::TYPE_NUMBER, 3),
					'alpha_if_removed_difference' => ilExteStatValue::_create($difference, ilExteStatValue::TYPE_NUMBER, 3, NULL, $indicator)	
			);
			$i++;
		}
		
		return $details;
	}
}