<?php

/**
 * Static functions, used by other OpenCPU-Evaluations. Offers limited interactive R-input.
 */
class ilExteEvalOpenCPU extends ilExteEvalTest
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
	protected $lang_prefix = 'tst_OpenCPU';

	/**
	 * Get the available parameters for this evaluation
	 * @return ilExteStatParam
	 */
	public function getAvailableParams()
	{
		return array(
				ilExteStatParam::_create('server', ilExteStatParam::TYPE_STRING, 'https://cloud.opencpu.org'),
		);
	}

	/**
	 * Collects data from an OpenCPU-session as array of json strings and plots as base64 string
	 * @param	string $server
	 * @param	string $data		A list of OpenCPU-session outputs
	 * @param	array  $needles		An array of strings to be loaded from the session
	 * @return 	array				An array containing the data
	 */
	public static function retrieveData($server, $data, $needles) {
		$results = array();
		$response_path = explode("\n", $data);

		foreach($needles as $needle) {
			if ($needle == 'graphics') {
				foreach($response_path as $path) {
					if(preg_match("/\b{$needle}\b/i", $path)) {
						//$results[$needle][] = file_get_contents($server . $path .'/svg');
						$results[$needle][] = base64_encode(file_get_contents($server . $path .'/png'));
					}
				}
			} else {
				foreach($response_path as $path) {
					if(preg_match("/{$needle}$/", $path)) {
						$results[$needle] = file_get_contents($server . $path .'/json');
					}
				}
			}
			
		}
		return $results;
	}
	
	/**
	 * Create customHTML with an accordion containing the plots from R for all IRT-Evaluations
	 * @param	object $object	The calling object, to get the language variables in this static context
	 * @param	array  $plots	An array containing the plots as base64 
	 * @return 	string			The customHTML with the plots in an accordion
	 * @see  	ilExteEvalOpenCPU::retrievePlots()
	 */
	public static function getPlotAccordionHTML($object, $plots){
		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU_Plots.html', TRUE, TRUE, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		
		try {
			//show TIC first, it's the last element of $plots
			$template->setCurrentBlock("accordion_plot");
			$template->setVariable('TITLE', $object->plugin->txt('tst_OpenCPU_graph_TIC'));
			$template->setVariable('PLOT', "<img src='data:image/png;base64," . end($plots) . "'>");
			$template->parseCurrentBlock("accordion_plot");
			
			//show IIC, it's the second last element of $plots
			$template->setCurrentBlock("accordion_plot");
			$template->setVariable('TITLE', $object->plugin->txt('tst_OpenCPU_graph_IIC'));
			$template->setVariable('PLOT', "<img src='data:image/png;base64," . prev($plots) . "'>");
			$template->parseCurrentBlock("accordion_plot");
			
			//show all IRCCC in a single accordion section
			$template->setCurrentBlock("accordion_plot");
			$template->setVariable('TITLE', $object->plugin->txt('tst_OpenCPUI_graph_IRCCC'));
			$plot = '';
			for ($i = 0; $i < count($plots)-2; $i++) {  //-2 because of TIC and IIC
				$plot .= "<img src='data:image/png;base64," . $plots[$i] . "'>";
			}
			$template->setVariable('PLOT', $plot);
			$template->parseCurrentBlock("plot");
			
			return $template->get();
		} catch (Exception $e) {
			return $e;
		}
	}
	
	/**
	 * Create a request for OpenCPU
	 * @param	string $server
	 * @param	string $path
	 * @param	string $data
	 * @return 	string 			JSON or Session-ID
	 */
	public static function callOpenCPU($server, $path, $data) {
		$options = array(
				'http' => array( // use key 'http' even if sending the request to https
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => http_build_query($data)
				)
		);
		$context  = stream_context_create($options);
		try {
			return file_get_contents($string = rtrim($server, '/') . $path, false, $context);
		} catch (Exception $e) {
			return FALSE;
		}
	}

	/**
	 * Transforms the points into dichotomous 0 and 1 outcomes
	 * Dichotomizing is a bad transformation with great information loss
	 * and should be avoided if possible.
	 * Switching between variants is currently for development only
	 * Possible variants:
	 * 		1. 50% of reachable points
	 * 		2. mean of reached points
	 * 		3. modal ...
	 * 		4. median ...
	 * 		5. specific value (general case of 1. and 2.)
	 * @param	array  	$answers 	The Points to be dichotomized
	 * @param	string	$version	The method used for dichotomizing
	 * @param	float	$value		The cut score
	 * @return 	integer $result	The dichotomized value
	 */
	public function dichotomize($answers, $version = 'value', $value = 0) {
		switch ($version){
		case 'modal':
			$counted = array_count_values($answers);
			asort($counted);
			$maxCount = max($counted);
			foreach($counted as $number => $count)
			{
				if($count == $maxCount)
					$modals[] = $number;
			}
			if (count($modals) != 1) {
				$value = $modals[ceil(count($modals)/2)];
			} else {
				$value = $modals[0];
			}
			break;
		case 'median':
			$sorted = $answers;
			rsort($sorted);
			$middle = round(count($sorted) / 2);
			$value = $sorted[$middle-1];
			break;
		}
			
			/*	50%  -> $value = $question->maximum_points/2
			 *  mean -> $value = $question->average_points
			 */
			foreach ($answers as $key => $answer)
			{
				if (is_numeric ($answer->reached_points) && $answer->reached_points <= $value) {
					$answers[$key]->reached_points = 0;
				} elseif (is_numeric ($answer->reached_points)) {
					$answers[$key]->reached_points = 1;
				}
			}
			return $answers;
	}

	/**
	 * Transform the available data to a csv-structure, save the removed items and if the data was dichotomous 
	 * @param	object  $object 		the calling class
	 * @param	bool	$dichotomize	trigger dichotomizing of the reached points
	 * @param	bool	$missingAsNA	leave missing answers as NA and don't code them as wrong (0 points)
	 * @param	bool	$rebaseData		transform data required for ltm::grm and ltm::gpcm
	 * @return 	array					[csv]			the basic data for R/OpenCPU as CSV
	 * 									[removed]		the removed items as question_id
	 * 									[dichotomous]	if the test was dichotomous after reworking the data
	 */
	public function getBasicData($object, $dichotomize = FALSE, $missingAsNA = FALSE, $rebaseData = TRUE) {
		
		$header_array = array();
		$active_id_array = array();
		$removed_id_array = array();
		$all_variants_array = array();
		
		foreach ($object->data->getAllQuestions() as $question)
		{
			//dichotomize?
			$answers = $dichotomize ?
			self::dichotomize($object->data->getAnswersForQuestion($question->question_id), 'value', $question->maximum_points/2)
			: 	$object->data->getAnswersForQuestion($question->question_id);
			
			// missingAsNA ? (keep missing data as NA or set as wrong)
			if(!$missingAsNA){
				foreach ($answers as $key => $answer)
				{
					if (!$answer->answered) {
						$answers[$key]->reached_points = 0;
					}
				}	
			}

			/* rebaseData?
			 * 1. Base for all points is 0
			 * 2. Distance between points is 1
			 * 3. Remove Questions without variance
			 */
			if($rebaseData)
			{
				//count different points given to remove items with zero variance
				$count = array();
				$count[] = 0;
				foreach ($answers as $answer)
				{
					if ($answer->answered && !in_array((float)$answer->reached_points, $count))
					{
						$count[] = (float)$answer->reached_points;
					}
				}
				sort($count);				
				$index = 0;
				foreach ($answers as $key => $answer)
				{
					if (in_array($answer->reached_points, $count)) {
						$answers[$key]->reached_points = array_search($answer->reached_points, $count);
					}
				}
				
				//only add items with variance
				$variants = count($count);
				array_push($all_variants_array, count($count));
				
				if($variants > 1){
					array_push($header_array, $question->question_id);
					foreach ($answers as $answer)
					{
						$active_id_array[$answer->active_id][$question->question_id] = $answer->reached_points;
					}
				} else {
					array_push($removed_id_array, $question->question_id);
				}
			} else {
				array_push($header_array, $question->question_id);
				foreach ($answers as $answer)
				{
					$active_id_array[$answer->active_id][$question->question_id] = $answer->reached_points;
				}
			}
		}

		// form data to a csv-string
		$csv = "";
		foreach ($header_array as $header){
			$csv .= ",". $header;
			
		}
		$csv .= "\n";
		
		foreach ($active_id_array as $active_id => $reached_points_array) {
			$csv .= $active_id;
			$numItems = count($reached_points_array);
			$i = 0;
			foreach ($reached_points_array as $reached_points){
				$csv .= "," . $reached_points;
				
				if(++$i === $numItems) {
					$csv .= "\n";
				}
			}
		}
		
		$csv = trim($csv, "\n ");
		//error_log($csv, 3, "Customizing/csv.log");
		//error_log(json_encode($array), 3, "Customizing/json.log");
		//error_log(json_encode($csv), 3, "Customizing/json.log");
		
		$dichotomous = FALSE;
		if (count(array_unique($all_variants_array)) === 1) {
			$dichotomous = TRUE;
		}

		/* adding information to the result: removed questions and if data is (now) dichotomous
		 * important for polytomous evaluations because ltm then returns a dataset without question_id
		 * and we have to handle the different datastructure
		 */ 
		$data = array('csv' => $csv, 'removed' => $removed_id_array, 'dichotomous' => $dichotomous);
		return $data;
	}

	/**
	 * Calculate the details for a test
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

		$data = ilExteEvalOpenCPU::getBasicData($this);

		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU.html', false, false, "Customizing/global/plugins/Modules/Test/Evaluations/ilIRTEvaluations");
		$template->setVariable('SERVER', $this->getParam('server'));
		$template->setVariable('CALLR_DESC', $this->plugin->txt('tst_OpenCPU_callR_desc'));
		$template->setVariable('CALLR', $this->plugin->txt('tst_OpenCPU_callR'));

		$result_array = array_map("str_getcsv", explode("\n", $data['csv']));
		$json = json_encode($result_array);
		$template->setVariable('JSON', substr_replace($json, 0, 3, 0));		
		
		$details->customHTML = $template->get();
		
        return $details;
	}
}