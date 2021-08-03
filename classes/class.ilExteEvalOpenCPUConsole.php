<?php

/**
 * Offers limited interactive R-input.
 */
class ilExteEvalOpenCPUConsole extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPUConsole';

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
	 * Calculate the details for a test
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

		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU.html', false, false, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setVariable('SERVER', $server);
		$template->setVariable('CALLR_DESC', $this->plugin->txt('tst_OpenCPU_callR_desc'));
		$template->setVariable('CALLR', $this->plugin->txt('tst_OpenCPU_callR'));

		$result_array = array_map("str_getcsv", explode("\n", $data['csv']));
		$json = json_encode($result_array);
		$template->setVariable('JSON', substr_replace($json, 0, 3, 0));		
		
		$details->customHTML = $template->get();
		
        return $details;
	}

}