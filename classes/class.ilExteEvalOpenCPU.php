<?php

/**
 * Raw data for a test, used by OpenCPU
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
				ilExteStatParam::_create('server', ilExteStatParam::TYPE_STRING, 'http://[OpenCPU-Server]'),
		);
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
		return file_get_contents($string = rtrim($server, '/') . $path, false, $context);
	}

	/**
	 * Transform the available data to a structure, readable for R
	 * @param	object  $object the calling class
	 * @return 	string			the basic data for R/OpenCPU as CSV
	 */
	public function getBasicData($object) {
		$header_array = array();
		
		foreach ($object->data->getAllQuestions() as $question)
		{
			array_push($header_array, $question->question_id);
		}
		$active_id_array = array();
		
		foreach ($object->data->getAllQuestions() as $question)
		{
			foreach ($object->data->getAnswersForQuestion($question->question_id) as $answer)
			{
				$active_id_array[$answer->active_id][$question->question_id] = $answer->reached_points;
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
		
		//TODO decide format
		//$array = array_map("str_getcsv", explode("\n", $csv));
		//$json = json_encode($array);
		//error_log($csv, 3, "Customizing/csv.log");
		//error_log(json_encode($array), 3, "json.log");
		
		return $csv;
	}

	/**
	 * Calculate the details for a test
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
		$csv = ilExteEvalOpenCPU::getBasicData($this);

		$template = new ilTemplate('tpl.il_exte_stat_OpenCPU.html', false, false, "Customizing/global/plugins\Modules/Test/Evaluations/ilIRTEvaluations");

		$details = new ilExteStatDetails();
		$details->customHTML = $template->get();
		
        return $details;
	}
}